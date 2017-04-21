<?php

use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\Api\Values\Content;


class DataHandlerBookingSalaPubblica implements OpenPADataHandlerInterface
{
    /**
     * @var eZContentObject
     */
    private $currentSalaObject;

    /**
     * @var eZContentObject
     */
    private $currentStuffObject;

    private $dataFunction;

    private $language = 'ita-IT';

    private $showUnavailableLocations = false;

    public function __construct(array $Params)
    {
        if (isset( $Params['UserParameters']['availability'] )) {
            $this->dataFunction = 'getAvailability';
        } else {
            $salaId = eZHTTPTool::instance()->getVariable('sala', false);
            if ($salaId) {
                $this->currentSalaObject = eZContentObject::fetch(intval($salaId));
                if (!$this->currentSalaObject instanceof eZContentObject) {
                    throw new Exception("Sala pubblica $salaId non trovata");
                }
            }
            $stuffId = eZHTTPTool::instance()->getVariable('stuff', false);
            if ($stuffId) {
                $this->currentStuffObject = eZContentObject::fetch(intval($stuffId));
                if (!$this->currentStuffObject instanceof eZContentObject) {
                    throw new Exception("Attrezzatura $salaId non trovata");
                }
            }

            $this->dataFunction = 'getCalendarData';

        }
    }

    public function getData()
    {
        return $this->{$this->dataFunction}();
    }

    /**
     * @param $query
     * @param array $limitation
     * @param bool $asObject
     * @param null $languageCode
     *
     * @return array()
     */
    private function findAll($query, array $limitation = null, $asObject = true, $languageCode = null)
    {
        $currentEnvironment = new FullEnvironmentSettings();
        $hits = array();
        $query .= ' and limit ' . $currentEnvironment->getMaxSearchLimit();
        eZDebug::writeNotice($query, __METHOD__);
        while ($query) {
            $results = $this->search($query, $limitation);
            $hits = array_merge($hits, $results->searchHits);
            $query = $results->nextPageQuery;
        }

        return $hits;
    }

    private function search($query, array $limitation = null)
    {
        $contentSearch = new ContentSearch();
        $contentSearch->setCurrentEnvironmentSettings(new FullEnvironmentSettings());
        try {
            return $contentSearch->search($query, $limitation);
        } catch (Exception $e) {
            eZDebug::writeError($e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $error = new \Opencontent\Opendata\Api\Values\SearchResults();
            $error->nextPageQuery = null;
            $error->searchHits = array();
            $error->totalCount = 0;
            $error->facets = array();
            $error->query = $query;

            return $error;
        }
    }

    private function getAvailabilityRequest()
    {
        $request = urldecode(eZHTTPTool::instance()->getVariable('q', false));
        parse_str($request, $requestData);
        $cleanRequestData = function ($value) {
            return str_replace('*', ' ', $value);
        };
        $cleanRequest = array_map($cleanRequestData, $requestData);

        $availabilityRequest = array();

        if (isset( $cleanRequest['from'] ) && isset( $cleanRequest['to'] )) {
            $availabilityRequest['calendar'] = "calendar[] = [{$cleanRequest['from']},{$cleanRequest['to']}]";
            $availabilityRequest['from'] = $cleanRequest['from'];
            $availabilityRequest['to'] = $cleanRequest['to'];
        }

        if (isset( $cleanRequest['stuff'] )) {
            $stuffList = array();
            if (!empty( $cleanRequest['stuff'] )) {
                $stuffList = explode('-', trim($cleanRequest['stuff']));
            }
            $availabilityRequest['stuff'] = $stuffList;
        } else {
            $availabilityRequest['stuff'] = array();
        }

        $filters = array();
        if (isset( $cleanRequest['numero_posti'] )) {
            switch ($cleanRequest['numero_posti']) {
                case '1':
                    $filters[] = 'raw[attr_numero_posti_si] range [*,100]';
                    break;
                case '2':
                    $filters[] = 'raw[attr_numero_posti_si] range [100,200]';
                    break;
                case '3':
                    $filters[] = 'raw[attr_numero_posti_si] range [200,400]';
                    break;
                case '4':
                    $filters[] = 'raw[attr_numero_posti_si] range [400,*]';
                    break;
            }
        }
        if (isset( $cleanRequest['destinazione_uso'] ) && !empty( $cleanRequest['destinazione_uso'] )) {
            $filters[] = "destinazione_uso = '" . $cleanRequest['destinazione_uso'] . "'";
        }
        if (isset( $cleanRequest['location'] )) {
            $location = (int)$cleanRequest['location'];
            if ($location > 0) {
                $filters[] = 'id = ' . $location;
            }
        }

        $availabilityRequest['filter_query'] = implode(' and ', $filters);

        return $availabilityRequest;
    }

    private function getAvailability()
    {
        $filterContent = new DefaultEnvironmentSettings();

        $request = $this->getAvailabilityRequest();

        $bookingQuery = null;
        $bookedLocations = array();
        $bookedStuff = array();

        if (isset( $request['calendar'] )) {
            $dateFilter = $request['calendar'];
            $stateIdList = array(
                ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_APPROVED)->attribute('id'),
                //            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject( ObjectHandlerServiceControlBookingSalaPubblica::STATUS_DENIED )->attribute( 'id' ),
                ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_PENDING)->attribute('id'),
                //            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject( ObjectHandlerServiceControlBookingSalaPubblica::STATUS_EXPIRED )->attribute( 'id' ),
                ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_CHECKOUT)->attribute('id'),
                ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_PAYMENT)->attribute('id')
            );
            $statusFilter = "and state in [" . implode(',', $stateIdList) . "]";

            $bookingQuery = "$dateFilter $statusFilter classes [prenotazione_sala]";
            $bookings = $this->findAll($bookingQuery, array());

            foreach ($bookings as $item) {

                $content = new Content($item);
                $booking = $filterContent->filterContent($content);

                $status = array_reduce($booking['metadata']['stateIdentifiers'], function ($carry, $item) {
                    $carry = '';
                    if (strpos($item, 'booking') === 0) {
                        $carry = str_replace('booking.', '', $item);
                    }

                    return $carry;
                });
                $status = ObjectHandlerServiceControlBookingSalaPubblica::getStateCodeFromIdentifier($status);

                if (isset( $booking['data'][$this->language]['sala'] ) && !empty( $booking['data'][$this->language]['sala'] )) {
                    foreach ($booking['data'][$this->language]['sala'] as $location) {
                        if (!isset( $bookedLocations[$location['id']] )) {
                            $bookedLocations[$location['id']] = array();
                        }
                        $bookedLocations[$location['id']][] = array(
                            'id' => $booking['metadata']['id'],
                            'status' => $status,
                            'owner' => $booking['metadata']['ownerId']
                        );
                    }
                }
                if (isset( $booking['data'][$this->language]['stuff'] ) && !empty( $booking['data'][$this->language]['stuff'] )) {
                    foreach ($booking['data'][$this->language]['stuff'] as $stuff) {
                        $bookedStuff[$stuff['id']][] = $stuff['extra']['in_context']['booking_status'];
                    }
                }
            }
        }

        $service = new ObjectHandlerServiceControlBookingSalaPubblica();
        $classes = implode(',', $service->bookableClassIdentifiers());

        $locationQuery = "{$request['filter_query']} classes [{$classes}]";
        $locations = $this->findAll($locationQuery, array());

        $geoJson = new FeatureCollection();
        $availableLocations = array();

        if (eZINI::instance()->variable('DebugSettings', 'DebugOutput') == 'enabled') {
            $geoJson->debug = array(
                'request' => $request,
                'bookings_query' => $bookingQuery,
                'locations_query' => $locationQuery
            );
        }

        foreach ($locations as $item) {

            $content = new Content($item);

            if (isset( $request['calendar'] )) {
                $object = $content->getContentObject($this->language);
                if (ObjectHandlerServiceControlBookingSalaPubblica::isValidDay(new DateTime($request['from']), new DateTime($request['to']), $object)
                ) {
                    $geoFeature = $content->geoJsonSerialize();
                    $locationAvailabilityInfo = $this->getLocationAvailabilityInfo($content, $bookedLocations);
                    $stuffAvailabilityInfo = $this->getStuffAvailabilityInfo($request['stuff'], $bookedLocations);
                    $location = $filterContent->filterContent($content);
                    $location['is_availability_request'] = true;
                    $location['location_available'] = (bool)$locationAvailabilityInfo['is_available'];
                    $location['location_self_booked'] = (int)$locationAvailabilityInfo['is_self_booked'];
                    $location['location_bookings'] = (int)$locationAvailabilityInfo['booking_count'];
                    $location['location_busy_level'] = (int)$locationAvailabilityInfo['busy_level'];
                    $location['stuff_available'] = (bool)$stuffAvailabilityInfo['is_available'];
                    $location['stuff_bookings'] = $stuffAvailabilityInfo['bookings'];
                    $location['stuff_busy_level'] = $stuffAvailabilityInfo['busy_level'];
                    $location['stuff_global_busy_level'] = $stuffAvailabilityInfo['global_busy_level'];
                    if ($location['location_busy_level'] <= 0 || $this->showUnavailableLocations) {

                        $geoFeature->properties->add('content', $location);
                        if ($geoFeature->geometry->coordinates){
                            $geoJson->add($geoFeature);
                        }
                        $availableLocations[] = $location;
                    }
                }
            }else{
                $geoFeature = $content->geoJsonSerialize();
                $location = $filterContent->filterContent($content);
                $location['is_availability_request'] = false;
                $geoFeature->properties->add('content', $location);
                if ($geoFeature->geometry->coordinates){
                    $geoJson->add($geoFeature);
                }
                $availableLocations[] = $location;
            }

        }

        return array(
            'geo' => $geoJson,
            'contents' => $availableLocations
        );
    }

    private function getLocationAvailabilityInfo(Content $content, $bookedLocations)
    {
        $isAvailable = true;
        $bookingCount = 0;
        $busyLevel = -1;
        $isSelfBooked = 0;
        foreach ($bookedLocations as $id => $values) {
            if ($id == $content->metadata->id) {
                $isAvailable = false;
                $bookingCount = count($values);
                $busyLevel = 0;
                foreach ($values as $value) {
                    $status = $value['status'];
                    if ($status == ObjectHandlerServiceControlBookingSalaPubblica::STATUS_APPROVED
                        || $status == ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_CHECKOUT
                        || $status == ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_PAYMENT) {
                        $busyLevel = 1;
                    }
                    $owner = $value['owner'];
                    if ($owner == eZUser::currentUserID()){
                        $isSelfBooked = $value['id'];
                    }
                }
            }
        }

        return array(
            'is_available' => $isAvailable,
            'booking_count' => $bookingCount,
            'busy_level' => $busyLevel,
            'is_self_booked' => $isSelfBooked
        );
    }

    private function getStuffAvailabilityInfo($requestStuffIdList, $bookedStuff)
    {

        $isAvailable = true;
        $bookingCount = array();
        $busyLevel = array();
        $globalBusyLevel = -1;

        foreach ($requestStuffIdList as $requestStuffId) {
            $bookingCount[$requestStuffId] = 0;
            $busyLevel[$requestStuffId] = -1;
            if (isset( $bookedStuff[$requestStuffId] )){
                $isAvailable = false;
                $bookingCount[$requestStuffId] = count($bookedStuff[$requestStuffId]);
                foreach($bookedStuff[$requestStuffId] as $status){
                    switch($status){
                        case 'pending':
                            $busyLevel[$requestStuffId] = 0;
                            if ($globalBusyLevel < 0){
                                $globalBusyLevel = 0;
                            }
                            break;

                        case 'approved':
                            $busyLevel[$requestStuffId] = 1;
                            if ($globalBusyLevel < 1){
                                $globalBusyLevel = 1;
                            }
                            break;

                        case 'denied':
                        case 'expired':
                            break;
                    }
                }
            }
        }

        return array(
            'is_available' => $isAvailable,
            'bookings' => $bookingCount,
            'busy_level' => $busyLevel,
            'global_busy_level' => $globalBusyLevel
        );
    }

    private function getCalendarData()
    {
        $data = array();

        $queryParts = array();

        $from = eZHTTPTool::instance()->getVariable('start', false);
        $end = eZHTTPTool::instance()->getVariable('end', false);
        $queryParts['calendar'] = "calendar[] = [{$from},{$end}]";

        $stateIdList = eZHTTPTool::instance()->getVariable('states', array(
            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_APPROVED)->attribute('id'),
            //          ObjectHandlerServiceControlBookingSalaPubblica::getStateObject( ObjectHandlerServiceControlBookingSalaPubblica::STATUS_DENIED )->attribute( 'id' ),
            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_PENDING)->attribute('id'),
            //          ObjectHandlerServiceControlBookingSalaPubblica::getStateObject( ObjectHandlerServiceControlBookingSalaPubblica::STATUS_EXPIRED )->attribute( 'id' ),
            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_CHECKOUT)->attribute('id'),
            ObjectHandlerServiceControlBookingSalaPubblica::getStateObject(ObjectHandlerServiceControlBookingSalaPubblica::STATUS_WAITING_FOR_PAYMENT)->attribute('id')
        ));
        $queryParts['state'] = "state in [" . implode(',', $stateIdList) . "]";

        if ($this->currentSalaObject instanceof eZContentObject) {
            $queryParts['subree'] = "subtree [{$this->currentSalaObject->attribute( 'main_node_id' )}]";
        }

        if ($this->currentStuffObject instanceof eZContentObject) {
            $field = 'raw[' . OpenPASolr::generateSolrSubMetaField('stuff', 'id') . ']';
            $queryParts['stuff'] = "$field = '{$this->currentStuffObject->attribute( 'id' )}'";
        }

        $queryParts['sort'] = "classes [prenotazione_sala] sort [from_time=>desc]";

        $query = implode(' and ', $queryParts);

        $hits = $this->findAll($query, array());

        foreach ($hits as $hit) {
            $item = $this->convertToCalendarItem($hit);
            if ($item) {
                $data[] = $item;
            }
        }

        return $data;
    }

    private function convertToCalendarItem($hit)
    {
        $current = eZHTTPTool::instance()->getVariable('current', array());
        $content = new Content($hit);
        $object = $content->getContentObject($this->language);

        $colors = ObjectHandlerServiceControlBookingSalaPubblica::getStateColors();
        $openpaObject = OpenPAObjectHandler::instanceFromObject($object);
        if ($openpaObject->hasAttribute('control_booking_sala_pubblica')
            && $openpaObject->attribute('control_booking_sala_pubblica')->attribute('is_valid')
        ) {
            $state = ObjectHandlerServiceControlBookingSalaPubblica::getStateObject($openpaObject->attribute('control_booking_sala_pubblica')->attribute('current_state_code'));
            if ($state instanceof eZContentObjectState) {
                $url = 'openpa_booking/view/sala_pubblica/' . $object->attribute('id');
                eZURI::transformURI($url);
                $item = new stdClass();
                $item->id = $object->attribute('id');
                $item->url = $url;

                if ($this->currentStuffObject instanceof eZContentObject) {
                    $statuses = $openpaObject->attribute('control_booking_sala_pubblica')->attribute('stuff_statuses');
                    $stuffStateName = '?';
                    if (isset( $statuses[$this->currentStuffObject->attribute('id')] )) {
                        $stuffStateName = $statuses[$this->currentStuffObject->attribute('id')];
                    }
                    $item->title = $stuffStateName;
                    if ($stuffStateName == 'pending') {
                        $item->color = $colors[ObjectHandlerServiceControlBooking::STATUS_PENDING];
                    } elseif ($stuffStateName == 'approved') {
                        $item->color = $colors[ObjectHandlerServiceControlBooking::STATUS_APPROVED];
                    } elseif ($stuffStateName == 'denied') {
                        $item->color = $colors[ObjectHandlerServiceControlBooking::STATUS_DENIED];
                    } elseif ($stuffStateName == 'expired') {
                        $item->color = $colors[ObjectHandlerServiceControlBooking::STATUS_EXPIRED];
                    } else {
                        $item->color = $colors['none'];
                    }
                } else {
                    $item->title = $state->attribute('current_translation')->attribute('name');
                    if (in_array($object->attribute('id'), $current)) {
                        $item->color = $colors['current'];
                        //$item->title = $object->attribute( 'owner' )->attribute( 'name' );
                    } elseif ($object->attribute('can_read')) {
                        $item->color = $colors[$openpaObject->attribute('control_booking_sala_pubblica')->attribute('current_state_code')];
                    } else {
                        $item->color = $colors['none'];
                    }
                }

                $item->start = $openpaObject->attribute('control_booking_sala_pubblica')->attribute('start')->format('c');
                $item->end = $openpaObject->attribute('control_booking_sala_pubblica')->attribute('end')->format('c');
                $item->allDay = $openpaObject->attribute('control_booking_sala_pubblica')->attribute('all_day');

                return $item;
            }
        }

        return null;
    }
}
