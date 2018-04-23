<?php
	require 'vendor/autoload.php';

	use Google\AdsApi\Common\OAuth2TokenBuilder;
	use Google\AdsApi\Dfp\DfpServices;
	use Google\AdsApi\Dfp\DfpSessionBuilder;
	use Google\AdsApi\Dfp\v201802\Order;
	use Google\AdsApi\Dfp\v201802\OrderService;
	use Google\AdsApi\Dfp\v201802\CostType;
	use Google\AdsApi\Dfp\v201802\CreativePlaceholder;
	use Google\AdsApi\Dfp\v201802\CreativeRotationType;
	use Google\AdsApi\Dfp\v201802\CustomCriteriaSet;
	use Google\AdsApi\Dfp\v201802\CustomTargetingValue;
	use Google\AdsApi\Dfp\v201802\CustomTargetingValueMatchType;
	use Google\AdsApi\Dfp\v201802\Goal;
	use Google\AdsApi\Dfp\v201802\GoalType;
	use Google\AdsApi\Dfp\v201802\InventoryTargeting;
	use Google\AdsApi\Dfp\v201802\LineItem;
	use Google\AdsApi\Dfp\v201802\LineItemService;
	use Google\AdsApi\Dfp\v201802\LineItemType;
	use Google\AdsApi\Dfp\v201802\Money;
	use Google\AdsApi\Dfp\v201802\Size;
	use Google\AdsApi\Dfp\v201802\StartDateTimeType;
	use Google\AdsApi\Dfp\v201802\CustomCriteriaComparisonOperator;
	use Google\AdsApi\Dfp\v201802\CustomCriteria;
	use Google\AdsApi\Dfp\v201802\RoadblockingType;
	use Google\AdsApi\Dfp\v201802\CustomTargetingService;
	use Google\AdsApi\Dfp\v201802\LineItemCreativeAssociationService;
	use Google\AdsApi\Dfp\v201802\LineItemCreativeAssociation;
	use Google\AdsApi\Dfp\v201802\Targeting;
	use Google\AdsApi\Dfp\Util\v201802\StatementBuilder;

	/**
	 * Which creative sizes do we want the line items to support?
	 */
	define("CREATIVE_SIZES", array(
		array(300, 250), // TODO
		array(468, 60), // TODO
		array(728, 90) // TODO
	));

	/**
	 * What are the IDs of the prebid creatives that we wish to associate with the line items? Needs to hold at least
	 * one ID.
	 */
	define("CREATIVE_IDS", array(
		"123456", // TODO
		"123456", // TODO
		"123456", // TODO
		"123456", // TODO
		"123456" // TODO
	));

	/**
	 * ID of the key value (hb_pb). You can see this by opening DFP -> Inventory -> Key-values, clicking the key value
	 * and copying the keyId from the URL.
	 */
	define("KEY_VALUE_ID", "123456");  // TODO

	/**
	 * Advertiser ID can be fetched from DFP -> Admin -> Companies.
	 * Click one of the companies and copy the ID from the URL.
	 */
	define("ADVERTISER_ID", "123456");  // TODO

	/**
	 * Trafficker will be the contact person / user associated with the line item.
	 * The ID can be fetched from DFP -> Admin -> Access & Authorization.
	 * Click one of the users and copy the ID from the URL.
	 */
	define("TRAFFICKER_ID", "123456");  // TODO

	/**
	 * Placement that line items should be targeted to.
	 */
	define("PLACEMENT_ID", "123456");  // TODO

	echo "\n";
	echo "* Authenticating with DFP\n";

	$oAuth2Credential = (new OAuth2TokenBuilder())
		->fromFile()
		->build();

	$session = (new DfpSessionBuilder())
		->fromFile()
		->withOAuth2Credential($oAuth2Credential)
		->build();


	$dfpServices = new DfpServices();
	$orderService = $dfpServices->get($session, OrderService::class);
	$lineItemService = $dfpServices->get($session, LineItemService::class);
	$customTargetingService = $dfpServices->get($session, CustomTargetingService::class);
	$licaService = $dfpServices->get($session, LineItemCreativeAssociationService::class);

	$keyValueMap = createAndReturnKeyValuesForHb($customTargetingService);

	/**
	 * Create 5 orders, each holding 400 line items. This means we'll cover every price point between $0.01 and $20.00.
	 */
	$orderId = createOrder($orderService, "Prebid $0.01 - $4.00");
	createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, 0.01, 4.00);

	$orderId = createOrder($orderService, "Prebid $4.01 - $8.00");
	createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, 4.01, 8);

	$orderId = createOrder($orderService, "Prebid $8.01 - $12.00");
	createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, 8.01, 12);

	$orderId = createOrder($orderService, "Prebid $12.01 - $16.00");
	createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, 12.01, 16);

	$orderId = createOrder($orderService, "Prebid $16.01 - $20.00");
	createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, 16.01, 20);

	function createAndReturnKeyValuesForHb($customTargetingService)
	{
		$keyValueMap = array();
		$pageSize = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$statementBuilder = (new StatementBuilder())->where('customTargetingKeyId = :customTargetingKeyId')
			->orderBy('id ASC')
			->limit($pageSize);

		$statementBuilder->withBindVariableValue("customTargetingKeyId", KEY_VALUE_ID);
		$totalResultSetSize = 0;
		$statementBuilder->offset(0);
		do {
			$page = $customTargetingService->getCustomTargetingValuesByStatement($statementBuilder->toStatement());

			if ($page->getResults() !== null) {
				$totalResultSetSize = $page->getTotalResultSetSize();
				foreach ($page->getResults() as $customTargetingValue) {
					$keyValueMap['price_' . $customTargetingValue->getName()] = $customTargetingValue->getId();
				}
			}
			$statementBuilder->increaseOffsetBy($pageSize);
		}
		while ($statementBuilder->getOffset() < $totalResultSetSize);

		for ($i = 0.01; $i <= 20; $i = $i + 0.01) {
			$price = round($i * 100);
			$price = str_replace(",", ".", $price / 100);
			if (strpos($price, ".") <= 0) {
				$price = "$price.00";
			}
			else if (strlen(substr($price, strpos($price, ".") + 1)) == 1) {
				$price .= "0";
			}

			if (!isset($keyValueMap['price_' . $price])) {
				$priceKeyValue = new CustomTargetingValue();
				$priceKeyValue->setCustomTargetingKeyId(KEY_VALUE_ID);
				$priceKeyValue->setDisplayName($price);
				$priceKeyValue->setName($price);
				$priceKeyValue->setMatchType(CustomTargetingValueMatchType::EXACT);
				$values = $customTargetingService->createCustomTargetingValues(array($priceKeyValue));
				$keyValueMap['price_' . $values[0]->getName()] = $values[0]->getId();

				echo "  + Created hb_pb value " . $values[0]->getName() . " \n";
			}
		}

		return $keyValueMap;
	}

	function createOrder($orderService, $name)
	{
		echo "\n";
		echo "* Setting up Order $name\n";

		$order = new Order();
		$order->setName($name);
		$order->setAdvertiserId(ADVERTISER_ID);
		$order->setTraffickerId(TRAFFICKER_ID);

		// Create the order on the server.
		$results = $orderService->createOrders([$order]);
		$orderId = "";
		foreach ($results as $i => $order) {
			echo "Order with ID " . $order->getId() . " and name '" . $order->getName() . "' was created. \n";
			$orderId = empty($orderId) ? $order->getId() : $orderId;
		}

		return $orderId;
	}

	function createLineItems($lineItemService, $licaService, $keyValueMap, $orderId, $firstPrice, $lastPrice)
	{
		echo "\n";
		echo "* Setting up Line Items\n";

		for ($i = $firstPrice; $i <= $lastPrice; $i = $i + 0.01) {
			$price = round($i * 100);
			$price = str_replace(",", ".", $price / 100);
			if (strpos($price, ".") <= 0) {
				$price = "$price.00";
			}
			else if (strlen(substr($price, strpos($price, ".") + 1)) == 1) {
				$price .= "0";
			}
			echo "  $price \n";

			createLineItem($lineItemService, $licaService, $keyValueMap, $orderId, $price);
		}
	}

	function createLineItem($lineItemService, $licaService, $keyValueMap, $orderId, $price)
	{
		$inventoryTargeting = new InventoryTargeting();
		$inventoryTargeting->setTargetedPlacementIds([PLACEMENT_ID]);

		$customCriteria1 = new CustomCriteria();
		$customCriteria1->setKeyId(KEY_VALUE_ID);
		$customCriteria1->setOperator(CustomCriteriaComparisonOperator::IS);
		$customCriteria1->setValueIds([$keyValueMap['price_' . $price]]);

		$topCustomCriteriaSet = new CustomCriteriaSet();
		$topCustomCriteriaSet->setChildren([$customCriteria1]);

		$targeting = new Targeting();
		$targeting->setInventoryTargeting($inventoryTargeting);
		$targeting->setCustomTargeting($topCustomCriteriaSet);

		$lineItem = new LineItem();
		$lineItem->setName("bid_$price");
		$lineItem->setOrderId($orderId);
		$lineItem->setTargeting($targeting);

		$lineItem->setLineItemType(LineItemType::PRICE_PRIORITY);
		$lineItem->setPriority(12);

		$lineItem->setStartDateTimeType(StartDateTimeType::IMMEDIATELY);
		$lineItem->setUnlimitedEndDateTime(true);

		$lineItem->setRoadblockingType(RoadblockingType::AS_MANY_AS_POSSIBLE);
		$lineItem->setCreativeRotationType(CreativeRotationType::EVEN);

		$goal = new Goal();
		$goal->setGoalType(GoalType::NONE);
		$lineItem->setPrimaryGoal($goal);

		$monetaryValue = $price * 1000000;
		$lineItem->setCostType(CostType::CPM);
		$lineItem->setCostPerUnit(new Money('USD', $monetaryValue));


		$creativePlaceholders = array();
		foreach (CREATIVE_SIZES as $size) {
			$creativePlaceholders[] = createSizePlaceHolder($size[0], $size[1]);
		}
		$lineItem->setCreativePlaceholders($creativePlaceholders);

		$results = $lineItemService->createLineItems([$lineItem]);

		$lineItemId = $results[0]->getId();

		foreach (CREATIVE_IDS as $id) {
			createLineitemCreativeAssociation($licaService, $lineItemId, $id);
		}
	}

	function createLineitemCreativeAssociation($licaService, $lineItemId, $creativeId)
	{
		$sizeOverrides = array();
		foreach (CREATIVE_SIZES as $size) {
			$override = new Size();
			$override->setWidth($size[0]);
			$override->setHeight($size[1]);
			$override->setIsAspectRatio(false);
			$sizeOverrides[] = $override;
		}

		$lica = new LineItemCreativeAssociation();
		$lica->setCreativeId($creativeId);
		$lica->setLineItemId($lineItemId);
		$lica->setSizes($sizeOverrides);
		$licaService->createLineItemCreativeAssociations([$lica]);
	}

	function createSizePlaceHolder($width, $height)
	{
		$size = new Size();
		$size->setWidth($width);
		$size->setHeight($height);
		$size->setIsAspectRatio(false);

		$creativePlaceholder = new CreativePlaceholder();
		$creativePlaceholder->setSize($size);

		return $creativePlaceholder;
	}

	echo "\nDONE\n\n";