<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$Http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$Part = $Params['Part'] ? $Params['Part'] : 'users';
$Offset = isset($Offset) ? $Offset : 0;
$viewParameters = array( 'offset' => $Offset, 'query' => null );
$currentUser = eZUser::currentUser();

$root = OpenPABooking::instance()->rootNode();

if ( $Http->hasVariable( 's' ) )
    $viewParameters['query'] = $Http->variable( 's' );

if ( $Http->hasVariable( 's' ) )
    $viewParameters['query'] = $Http->variable( 's' );

if ( $Http->hasPostVariable( 'AddModeratorLocation' ) || $Http->hasPostVariable( 'AddExternalUsersLocation' )  )
{
    $action = $Http->hasPostVariable( 'AddModeratorLocation' ) ? 'AddModeratorLocation' : 'AddExternalUsersLocation';

    eZContentBrowse::browse( array( 'action_name' => $action,
        'return_type' => 'NodeID',
        'class_array' => eZUser::fetchUserClassNames(),
        'start_node' => eZINI::instance('content.ini')->variable('NodeSettings','UserRootNode'),
        'cancel_page' => '/openpa_booking/config/moderators',
        'from_page' => '/openpa_booking/config/moderators' ), $Module );
    return;
}

if ( $Http->hasPostVariable('BrowseActionName') &&  $Http->postVariable('BrowseActionName') == 'AddModeratorLocation' )
{
    $nodeIdList = $Http->postVariable( 'SelectedNodeIDArray' );

    $newLocation = OpenPABooking::moderatorGroupNodeId();

    $redirect = 'moderators';

    foreach( $nodeIdList as $nodeId )
    {
        $node = eZContentObjectTreeNode::fetch($nodeId);
        if ($node instanceof eZContentObjectTreeNode){
            eZContentOperationCollection::addAssignment($nodeId, $node->attribute( 'contentobject_id' ), array($newLocation));
        }
    }
    $Module->redirectTo( '/openpa_booking/config/' . $redirect );
    return;
}

if ( $Part == 'users' )
{
    $usersParentNode = eZContentObjectTreeNode::fetch( intval( eZINI::instance()->variable( "UserSettings", "DefaultUserPlacement" ) ) );
    $userClass = eZContentClass::fetch(intval( eZINI::instance()->variable( "UserSettings", "UserClassID" ) ));
	$tpl->setVariable( 'user_class', $userClass );
    $tpl->setVariable( 'user_parent_node', $usersParentNode );
}
elseif ( $Part == 'moderators' )
{
    $tpl->setVariable( 'moderators_parent_node_id', OpenPABooking::moderatorGroupNodeId() );
}

$data = array();
/** @var eZContentObjectTreeNode[] $otherFolders */
$otherFolders = eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include',
                                                                 'ClassFilterArray' => array( 'folder' ),
                                                                 'Depth' => 1, 'DepthOperator' => 'eq', ),
                                                         $root->attribute( 'node_id' ) );
foreach( $otherFolders as $folder )
{
    $data[] = $folder;
}

$tpl->setVariable( 'root', $root );
$tpl->setVariable( 'current_user', $currentUser );
$tpl->setVariable( 'persistent_variable', array() );
$tpl->setVariable( 'view_parameters', $viewParameters );
$tpl->setVariable( 'current_part', $Part );
$tpl->setVariable( 'data', $data );

$Result = array();
$Result['persistent_variable'] = $tpl->variable( 'persistent_variable' );
$Result['content'] = $tpl->fetch( 'design:booking/config.tpl' );
$Result['node_id'] = 0;

$contentInfoArray = array( 'url_alias' => 'booking/config' );
$contentInfoArray['persistent_variable'] = false;
if ( $tpl->variable( 'persistent_variable' ) !== false )
{
    $contentInfoArray['persistent_variable'] = $tpl->variable( 'persistent_variable' );
}
$Result['content_info'] = $contentInfoArray;
$Result['path'] = array();
