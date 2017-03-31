<?php

class BookingHandlerSalaPubblica extends BookingHandlerBase implements OpenPABookingHandlerInterface
{
    /**
     * @var eZTemplate
     */
    protected $tpl;

    /**
     * @var eZModule
     */
    protected $module;

    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @param eZContentObject
     */
    protected $currentObject;

    /**
     * @var bool
     */
    protected $hasRedirect;

    public static function name()
    {
        return 'Prenotazioni sale pubbliche';
    }

    public static function identifier()
    {
        return 'sala_pubblica';
    }

    public function serviceClass()
    {
        return new ObjectHandlerServiceControlBookingSalaPubblica();
    }

    /**
     * @param eZCollaborationItem|eZContentObject $item
     *
     * @return null|ObjectHandlerServiceControlBookingSalaPubblica
     */
    protected function serviceObject($item)
    {
        return parent::serviceObject($item);
    }

    public function view()
    {
        $serviceClass = $this->serviceClass();
        if ($this->currentObject instanceof eZContentObject) {
            $serviceObject = $this->serviceObject($this->currentObject);
            if (!$serviceObject->getCollaborationItem() instanceof eZCollaborationItem) {
                $parent = $this->currentObject->mainNode()->fetchParent();
                if ($parent->attribute('class_identifier') == $serviceClass->prenotazioneClassIdentifier()) {
                    $this->currentObject = $parent->object();
                }
            }
            $serviceObject = $this->serviceObject($this->currentObject);
            $openpaObject = OpenPAObjectHandler::instanceFromContentObject($this->currentObject);
            if ($serviceObject
                && $serviceObject->isValid()
                && $this->currentObject->attribute('can_read')
            ) {

                $collaborationItem = $serviceObject->getCollaborationItem();
                if ($collaborationItem instanceof eZCollaborationItem) {
                    $module = eZModule::exists("collaboration");

                    return $module->run('item', array('full', $collaborationItem->attribute('id')));

                } else {
                    throw new Exception("eZCollaborationItem not found for object {$this->currentObject->attribute( 'id' )}");
                }
            }
        } else {
            $Result = array();
            $Result['content'] = $this->tpl->fetch('design:booking/sala_pubblica/list.tpl');
            $Result['path'] = array(array('text' => 'Prenotazione', 'url' => false));

            return $Result;
        }

        return null;
    }

    public function workflow($parameters, $process, $event)
    {
        $trigger = $parameters['trigger_name'];
        eZDebug::writeNotice("Run workflow $trigger", __METHOD__);
        if ($trigger == 'post_publish') {
            $currentObject = eZContentObject::fetch($parameters['object_id']);
            $openpaObject = OpenPAObjectHandler::instanceFromContentObject($currentObject);

            /** @var ObjectHandlerServiceControlBookingSalaPubblica $serviceObject */
            $serviceObject = $openpaObject->serviceByClassName(get_class($this->serviceClass()));

            if ($serviceObject && $serviceObject->attribute('is_valid')) {
                if ($currentObject->attribute('class_identifier') == $serviceObject->prenotazioneClassIdentifier()) {
                    if ($currentObject->attribute('current_version') == 1) {
                        self::initializeApproval($currentObject, $serviceObject);
                    } else {
                        self::restoreApproval($currentObject, $serviceObject);
                    }
                }
            }
        }
        if ($trigger == 'post_checkout') {
            /** @var eZOrder $order */
            $order = eZOrder::fetch($parameters['order_id']);
            if ($order instanceof eZOrder) {
                $products = $order->attribute('product_items');
                foreach ($products as $product) {
                    /** @var eZContentObject $prenotazione */
                    $prenotazione = $product['item_object']->attribute('contentobject');
                    /** @var ObjectHandlerServiceControlBookingSalaPubblica $service */
                    $service = $this->serviceObject($prenotazione);
                    if ($service
                        && $service->isValid()
                        && $prenotazione->attribute('can_read')
                    ) {
                        $service->addOrder($order);
                    }
                }
            }
        }
        if ($trigger == 'pre_delete') {
            $nodeIdList = $parameters['node_id_list'];
            $inTrash = (bool) $parameters['move_to_trash'];
            foreach( $nodeIdList as $nodeId )
            {
                $object = eZContentObject::fetchByNodeID( $nodeId );
                if ( $object instanceof eZContentObject
                     && $object->attribute('class_identifier') == $this->serviceClass()->prenotazioneClassIdentifier() )
                {
                    try
                    {
                        $this->removeBooking( $object, $inTrash );
                    }
                    catch( Exception $e )
                    {
                        eZDebug::writeError( $e->getMessage(), __METHOD__ );
                    }
                }
            }
        }
    }

    /**
     * @param eZContentObject $object
     * @param bool $moveInTrash
     *
     * @throws Exception
     */
    private function removeBooking( eZContentObject $object, $moveInTrash )
    {
        if (!$moveInTrash) {
            $serviceObject = $this->serviceObject($object);
            if ($serviceObject){
                $collaborationItem = $serviceObject->getCollaborationItem();
                if ($collaborationItem instanceof eZCollaborationItem) {
                    $itemId = $collaborationItem->attribute('id');
                    $db = eZDB::instance();
                    $db->begin();
                    $db->query("DELETE FROM ezcollab_item WHERE id = $itemId");
                    $db->query("DELETE FROM ezcollab_item_group_link WHERE collaboration_id = $itemId");
                    $res = $db->arrayQuery("SELECT message_id FROM ezcollab_item_message_link WHERE collaboration_id = $itemId");
                    foreach ($res as $r) {
                        $db->query("DELETE FROM ezcollab_simple_message WHERE id = {$r['message_id']}");
                    }
                    $db->query("DELETE FROM ezcollab_item_message_link WHERE collaboration_id = $itemId");
                    $db->query("DELETE FROM ezcollab_item_participant_link WHERE collaboration_id = $itemId");
                    $db->query("DELETE FROM ezcollab_item_status WHERE collaboration_id = $itemId");
                    $db->commit();
                }
            }

        }
    }

    public function approve(eZCollaborationItem $item, $parameters = array())
    {
        $prenotazione = OpenPABookingCollaborationHandler::contentObject($item);
        $serviceObject = $this->serviceObject($item);
        if ($serviceObject
            && $serviceObject->isValid()
            && $prenotazione->attribute('can_read')
        ) {
            $serviceObject->changeState(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_APPROVED);
            $serviceObject->setOrderStatus(eZOrderStatus::DELIVERED);
            $this->denyConcurrentRequests($serviceObject);
        }
    }

    private function denyConcurrentRequests(ObjectHandlerServiceControlBookingSalaPubblica $serviceObject)
    {
        $concurrentRequests = (array)$serviceObject->attribute('concurrent_requests');
        eZDebug::writeDebug("Deny concurrent " . count($concurrentRequests), __METHOD__);
        foreach ($concurrentRequests as $concurrentRequest) {
            if ($concurrentRequest instanceof eZContentObjectTreeNode) {
                $concurrentRequestService = $this->serviceObject($concurrentRequest->attribute('object'));
                if ($concurrentRequestService) {
                    $concurrentRequestCollaborationItem = $concurrentRequestService->getCollaborationItem();
                    if ($concurrentRequestCollaborationItem instanceof eZCollaborationItem) {
                        $concurrentRequestService->changeState(
                            ObjectHandlerServiceControlBookingSalaPubblica::STATUS_DENIED,
                            false
                        );
                        OpenPABookingCollaborationHandler::changeApprovalStatus(
                            $concurrentRequestCollaborationItem,
                            OpenPABookingCollaborationHandler::STATUS_DENIED
                        );
                    }
                }
            }
        }
    }

    public function defer(eZCollaborationItem $item, $parameters = array())
    {
        $prenotazione = OpenPABookingCollaborationHandler::contentObject($item);
        $serviceObject = $this->serviceObject($prenotazione);
        if ($serviceObject
            && $serviceObject->isValid()
            && $prenotazione->attribute('can_read')
        ) {
            $do = true;
            if ($serviceObject->attribute('has_manual_price')) {
                $do = false;
                if (isset( $parameters['manual_price'] )) {
                    $manualPrice = $parameters['manual_price'];
                    if (empty( $manualPrice ) || !preg_match("#^[0-9]+(.){0,1}[0-9]{0,2}$#", $manualPrice)) {
                        $error = "Prezzo non valido";
                        throw new Exception($error);
                    } else {
                        $do = $serviceObject->setPrice($manualPrice);
                    }
                }
            }

            if ($do) {
                $sala = $serviceObject->attribute('sala');
                if ($sala instanceof eZContentObject) {
                    $productType = eZShopFunctions::productTypeByObject($sala);
                    if ($productType) {
                        $serviceObject->changeState(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_CHECKOUT);
                    } else {
                        $serviceObject->changeState(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_APPROVED);
                        OpenPABookingCollaborationHandler::changeApprovalStatus(
                            $item,
                            OpenPABookingCollaborationHandler::STATUS_ACCEPTED
                        );
                    }
                }
                $this->denyConcurrentRequests($serviceObject);
            }
        }
    }

    public function deny(eZCollaborationItem $item, $parameters = array())
    {
        $prenotazione = OpenPABookingCollaborationHandler::contentObject($item);
        /** @var ObjectHandlerServiceControlBookingSalaPubblica $service */
        $service = $this->serviceObject($prenotazione);
        if ($service
            && $service->isValid()
            && $prenotazione->attribute('can_read')
        ) {
            $service->changeState(ObjectHandlerServiceControlBooking::STATUS_DENIED);
            if (!eZOrderStatus::fetchByStatus(9999)) {
                $row = array(
                    'id' => null,
                    'is_active' => true,
                    'status_id' => 9999,
                    'name' => ezpI18n::tr('kernel/shop', 'Annullato')
                );
                $newCustom = new eZOrderStatus($row);
                $newCustom->storeCustom();
            }
            $service->setOrderStatus(9999);
        }
    }

    private static function createSubRequest(
        eZContentObject $object,
        ObjectHandlerServiceControlBookingSalaPubblica $serviceObject
    ) {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $object->attribute('data_map');
        if (isset( $dataMap['scheduler'] ) && $dataMap['scheduler']->hasContent()) {
            $data = json_decode($dataMap['scheduler']->content(), 1);
            foreach ($data as $item) {
                $params = array(
                    'class_identifier' => $object->attribute('class_identifier'),
                    'parent_node_id' => $object->attribute('main_node_id'),
                    'attributes' => array(
                        'from_time' => $item['from'] / 1000,
                        'to_time' => $item['to'] / 1000,
                        'stuff' => empty( $item['stuff'] ) ? null : $item['stuff'],
                        'sala' => $dataMap['sala']->toString(),
                        'subrequest' => 1
                    )
                );
                $object = eZContentFunctions::createAndPublishObject($params);

            }
        }
    }

    private static function initializeApproval(
        eZContentObject $currentObject,
        ObjectHandlerServiceControlBookingSalaPubblica $serviceObject
    ) {
        if (in_array($currentObject->attribute('main_node')->attribute('parent')->attribute('class_identifier'),
            $serviceObject->salaPubblicaClassIdentifiers())
        ) {
            self::createUpdateApproval($currentObject, $serviceObject, true);
        }
    }

    private static function createUpdateApproval(
        eZContentObject $currentObject,
        ObjectHandlerServiceControlBookingSalaPubblica $serviceObject,
        $createSubRequest = false
    ) {
        $id = (int)$currentObject->attribute('id');
        $authorId = (int)$currentObject->attribute('owner_id');
        $approveIdArray = $serviceObject->getApproverIds();

        $participants = array_merge(array($authorId), $approveIdArray);
        self::addApprovalParticipants($participants);

        $exists = eZPersistentObject::fetchObject(
            eZCollaborationItem::definition(), null,
            array(
                'data_int1' => $id,
                'data_text1' => self::identifier()
            )
        );

        $collaborationItem = OpenPABookingCollaborationHandler::createApproval(
            $id,
            self::identifier(),
            $authorId,
            $approveIdArray,
            $exists
        );
        if ($exists) {
            $serviceObject->notify('edit_approval');
            eZDebug::writeNotice("Edit collaboration item", __METHOD__);
        } else {
            $serviceObject->notify('create_approval');
            eZDebug::writeNotice("Create collaboration item", __METHOD__);
        }

        if ($createSubRequest) {
            self::createSubRequest($currentObject, $serviceObject);
        }

        if (in_array($authorId, $approveIdArray)) {
            OpenPABookingCollaborationHandler::handler($collaborationItem)->approve($collaborationItem, array());
            OpenPABookingCollaborationHandler::changeApprovalStatus($collaborationItem,
                OpenPABookingCollaborationHandler::STATUS_ACCEPTED);
        } elseif ($exists) {
            $serviceObject->changeState(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_PENDING);
        }
    }

    private static function addApprovalParticipants(array $participantsIdList)
    {
        $notificationRules = eZPersistentObject::fetchObjectList(
            eZCollaborationNotificationRule::definition(),
            array('user_id'),
            array(
                'user_id' => array($participantsIdList),
                'collab_identifier' => OpenPABookingCollaborationHandler::TYPE_STRING
            ), null, null, false
        );
        $alreadyMembers = array();
        foreach ($notificationRules as $notificationRule) {
            $alreadyMembers[] = $notificationRule['user_id'];
        }
        $alreadyMembers = array_unique($alreadyMembers);
        $addIdList = array_diff($participantsIdList, $alreadyMembers);

        foreach ($addIdList as $addId) {
            $rule = eZCollaborationNotificationRule::create(
                OpenPABookingCollaborationHandler::TYPE_STRING,
                $addId
            );
            $rule->store();
        }
    }

    private static function restoreApproval(
        eZContentObject $currentObject,
        ObjectHandlerServiceControlBookingSalaPubblica $serviceObject
    ) {
        self::createUpdateApproval($currentObject, $serviceObject);
    }

    private function updateBasket(eZContentObject $prenotazione)
    {
        $serviceObject = $this->serviceObject($prenotazione);
        if ($serviceObject
            && $serviceObject->isValid()
            && $prenotazione->attribute('can_read')
        ) {
            $quantity = 1;
            if (!$serviceObject->attribute('has_manual_price')) {
                $quantity = $serviceObject->attribute('timeslot_count');
            }
            if (!$this->module instanceof eZModule) {
                throw new Exception("eZModule non trovato");
            }
            $this->module->redirectTo("/shop/add/" . $prenotazione->attribute('id') . "/" . $quantity);

            return null;
        } else {
            throw new Exception("Prenotazione non accessibile");
        }
    }

}
