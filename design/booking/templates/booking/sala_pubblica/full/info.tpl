{def $current = array($content_object.id)}
<div class="row">
    <div class="col-md-12" style="font-size: 1.3em;">
        <div class="panel panel-info">

            <div class="panel-heading"><b>{'Prenotazioni richieste'|i18n('booking')}</b></div>
            <table class="table">
                <tr>
                    <th>{'Luogo'|i18n('booking')}</th>
                    <td colspan="2">
                        <a href="{concat('openpa_booking/locations/', $sala.main_node_id)|ezurl(no)}">{$sala.name|wash()}</a>
                        <a href="#calendarModal" role="button" data-key="sala" data-param="{$sala.id}" data-name="{$sala.name|wash()}" data-toggle="modal"><i class="fa fa-calendar"></i> </a>
                    </td>
                </tr>
                <tr>
                    <th>{'Data'|i18n('booking')}</th>
                    <th>{'Inizio'|i18n('booking')}</th>
                    <th>{'Termine'|i18n('booking')}</th>
                </tr>
                <tr>
                    <td>{$content_object.data_map.from_time.content.timestamp|l10n(date)}</td>
                    <td>{$content_object.data_map.from_time.content.timestamp|l10n(shorttime)}</td>
                    <td>{$content_object.data_map.to_time.content.timestamp|l10n(shorttime)}</td>
                </tr>
                {if $content_object.main_node.children_count}
                    {foreach $content_object.main_node.children as $child}
                        {set $current = $current|append($child.contentobject_id)}
                        <tr {if and( is_set($node), $child.node_id|eq($node.node_id))}class="warning"{/if}>
                            <td>{$child.data_map.from_time.content.timestamp|l10n(date)}</td>
                            <td>{$child.data_map.from_time.content.timestamp|l10n(shorttime)}</td>
                            <td>
                                {$child.data_map.to_time.content.timestamp|l10n(shorttime)}
                                {if fetch('user', 'current_user').contentobject_id|eq(14)}
                                    <a class="pull-right" href="{$child.url_alias|ezurl(no)}">
                                        <span class="label label-default"><i class="fa fa-link"></i> {$child.contentobject_id}</span>
                                    </a>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                {/if}
            </table>
            <div class="panel-heading"><b>{'Attrezzatura richiesta'|i18n('booking')}</b></div>
            <table class="table">
                {if $content_object.data_map.stuff.has_content}

                    <tr>
                        <th>Attrezzatura</th>
                        <th>Responsabile</th>
                        <th>Stato richiesta</th>
                        <th></th>
                    </tr>
                    {def $stuff_list = $content_object.data_map.stuff.content.relation_list}
                    {if is_set($node)}
                        {set $stuff_list = $node.data_map.stuff.content.relation_list}
                    {/if}
                    {foreach $stuff_list as $item}
                        {def $stuff = fetch(content, object, hash(object_id, $item.contentobject_id))
                             $stuff_manager_ids = array()}
                        {foreach $stuff.data_map.reservation_manager.content.relation_list as $user}
                            {set $stuff_manager_ids = $stuff_manager_ids|append($user.contentobject_id)}
                        {/foreach}
                        <tr>
                            <td>
                                {$stuff.name|wash()}
                                <a href="#calendarModal" role="button" data-key="stuff" data-param="{$stuff.id}" data-name="{$stuff.name|wash()}" data-toggle="modal"><i class="fa fa-calendar"></i> </a>
                            </td>
                            <td>
                                {attribute_view_gui attribute=$stuff.data_map.reservation_manager}
                            </td>
                            <td>
                                {if is_set($item.extra_fields.booking_status)}
                                    <span class="label label-{$item.extra_fields.booking_status.identifier}">
                                        {$item.extra_fields.booking_status.value|wash()}
                                    </span>
                                {/if}
                            </td>
                            <td>
                                {if and($item.extra_fields.booking_status.identifier|eq('pending'), $stuff_manager_ids|contains(fetch('user', 'current_user').contentobject_id))}
                                <form method="post" action={"collaboration/action/"|ezurl} xmlns="http://www.w3.org/1999/html">
                                    <input type="hidden" name="Collaboration_OpenpaBookingActionParameters[stuff_id]" value="{$stuff.id}" />
                                    <input class="btn btn-success btn-xs" type="submit" name="CollaborationAction_AcceptStuff" value="Approva" />
                                    <input class="btn btn-danger btn-xs" type="submit" name="CollaborationAction_DenyStuff" value="Rifiuta" />
                                    <input type="hidden" name="CollaborationActionCustom" value="custom"/>
                                    <input type="hidden" name="CollaborationTypeIdentifier" value="openpabooking"/>
                                    <input type="hidden" name="CollaborationItemID" value="{$collab_item.id}"/>
                                </form>
                                {/if}
                            </td>
                        </tr>
                        {undef $stuff $stuff_manager_ids}
                    {/foreach}
                {else}
                    <tr>
                        <td><em>Nessuna</em></td>
                    </tr>
                {/if}
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{'Informazioni sulla richiesta'|i18n('booking')}</b></div>
            <table class="table">
                <tr>
                    <th>{'Data di invio della richiesta'|i18n('booking')}</th>
                    <td>
                        {$content_object.published|l10n(shortdatetime)}
                        {if $content_object.published|ne($content_object.modified)}
                            <br/>
                            <small>{'Ultima modifica'|i18n('booking')} {$content_object.modified|l10n(shortdatetime)}</small>
                        {/if}
                    </td>
                </tr>
                <tr>
                    <th>{'Stato della richiesta'|i18n('booking')}</th>
                    <td>
                        {$openpa_object.control_booking_sala_pubblica.current_state.current_translation.name|wash()}
                    </td>
                </tr>
                {foreach $participant_list as $item}
                    <tr>
                        <th>
                            {switch match=$item.name}

                            {case match="Autore"}
                                Richiedente
                            {/case}

                            {case match="Osservatore"}
                                Responsabile dell'attrezzatura
                            {/case}

                            {case match="Approvato da"}
                                Responsabile della sala
                            {/case}

                            {case}
                            {$item.name|wash}
                            {/case}

                            {/switch}
                        </th>
                        <td>{foreach $item.items as $partecipant}{$partecipant.participant.contentobject.name|wash()}{delimiter}, {/delimiter}{/foreach}</td>
                    </tr>
                {/foreach}

            </table>
        </div>

        <div class="panel panel-default">
            <table class="table">

                <tr>
                    <th>{'Costo previsto'|i18n('booking')}</th>
                    <td>
                        {def $price = $content_object.data_map.price.content.inc_vat_price}
                        {$price|sum($price|mul($content_object.main_node.children_count))|l10n( currency )}
                    </td>
                </tr>

                {if $content_object.data_map.order_id.content|gt(0)}
                    <tr>

                        {def $order = fetch('shop', 'order', hash('order_id', $content_object.data_map.order_id.content))}
                        {if $collab_item.is_creator}
                            <th>{'Ordine'|i18n('booking')}</th>
                            <td><a href="{concat('shop/orderview/', $order.id)|ezurl(no)}">{$order.status.name|wash()}</a></td>
                        {else}

                        <th><a href="{concat('shop/orderview/', $order.id)|ezurl(no)}">{'Ordine'|i18n('booking')}</a></th>
                        <td>

                            {def $order_status_list=$order.status_modification_list}
                            {if $order_status_list|count|gt( 0 )}
                            <form name="orderlist" method="post" action={'/shop/orderlist'|ezurl}>
                                <select name="StatusList[{$order.id}]">
                                    {foreach $order_status_list as $item}
                                        <option value="{$item.status_id}"
                                                {if eq( $item.status_id, $order.status_id )}selected="selected"{/if}>
                                            {$item.name|wash}</option>
                                    {/foreach}
                                </select>
                                <input class="button" type="submit" name="SaveOrderStatusButton" value="{'Apply changes'|i18n( 'design/admin/shop/orderlist' )}" />
                                </form>
                            {else}
                                <a href="{concat('shop/orderview/', $order.id)|ezurl(no)}">{$order.status.name|wash()}</a>
                            {/if}
                            {undef $order_status_list}
                        </td>
                        {/if}

                    </tr>
                {/if}
            </table>

        </div>

        <div class="panel panel-default">
            <table class="table">
                {foreach $content_object.data_map as $identifier => $attribute}
                    {if array('text', 'destinatari', 'patrocinio', 'comunicazione')|contains($attribute.contentclass_attribute_identifier)}
                        <tr>
                        <th>{$attribute.contentclass_attribute_name|wash()}</th>
                        <td>
                            {attribute_view_gui attribute=$attribute}
                        </td>
                        </tr>
                    {/if}
                {/foreach}

            </table>

        </div>
    </div>


    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><b>{'Iter della richiesta'|i18n('booking')}</b></div>
            <div class="panel-body">
                {include uri='design:booking/sala_pubblica/full/messages.tpl'}
            </div>
        </div>
    </div>
</div>

{include uri='design:booking/parts/status-style.tpl'}

<div id="calendarModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="width: 90%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                <h3 id="calendarModalTitle"></h3>
            </div>
            <div class="modal-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

{ezscript_require( array(
    'ezjsc::jquery',
    'jquery.opendataTools.js',
    'moment-with-locales.min.js',
    'fullcalendar/fullcalendar.js',
    'fullcalendar/locale/it.js',
    'fullcalendar/locale/de.js'
))}
{ezcss_require( array( 'fullcalendar.min.css' ) )}

{def $min_time = "07:00:00"
     $max_time = "24:00:00"
     $booking_url = "openpa/data/booking_sala_pubblica/"|ezurl(no)}
{def $current_language=ezini('RegionalSettings', 'Locale')}
{def $moment_language = $current_language|explode('-')[1]|downcase()}

{literal}
    <style>
        .fc-time-grid .fc-slats td {
            height: 33px;
        }
    </style>
<script>
    $(document).ready(function () {
        var calendar = $('#calendar');
        var calendarModalTitle = $("#calendarModalTitle");
        calendar.fullCalendar({
            locale: "{/literal}{$moment_language}{literal}",
            defaultView: "month",
            allDaySlot: false,
            slotDuration: '00:60:00',
            minTime: "{/literal}{$min_time}{literal}",
            maxTime: "{/literal}{$max_time}{literal}",
            contentHeight: 600,
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            loading: function (isLoading) {
                if (isLoading == true) {
                    calendarModalTitle.html('<i class="fa fa-circle-o-notch fa-spin fa-2x fa-fw"></i>');
                }else {
                    calendarModalTitle.html(calendarModalTitle.data('name'));
                }
            },
            events: {
                url: "{/literal}{$booking_url}{literal}",
                data: function(){
                    //console.log($('#calendarModal').data('query'));
                    var query = $('#calendarModal').data('query');
                    query['current'] = [{/literal}{$current|implode(',')}{literal}];
                    return query;
                }
            },
            selectable: false,
            eventClick: function(event) {
                if (event.url) {
                    window.open(event.url);
                    return false;
                }
            }
        });
        $('#calendarModal').on('shown.bs.modal', function (e) {
            //console.log($(e.relatedTarget).data());
            var name = $(e.relatedTarget).data('name');
            calendarModalTitle.data('name', name).html(name);
            var query = [];
            query[$(e.relatedTarget).data('key')] = $(e.relatedTarget).data('param');

            $('#calendarModal').data('query', query);

            calendar.fullCalendar('removeEvents');
            calendar.fullCalendar('render');
            calendar.fullCalendar('refetchEvents');
        });
    });

</script>
{/literal}
