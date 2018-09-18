{* DO NOT EDIT THIS FILE! Use an override template instead. *}
{set-block scope=root variable=subject}{"Order"|i18n("design/standard/shop")}: {$order.order_nr}{/set-block}
{set-block scope=root variable=content_type}text/html{/set-block}



{shop_account_view_gui view=html order=$order}

{def $currency = fetch( 'shop', 'currency', hash( 'code', $order.productcollection.currency_code ) )
     $locale = false()
     $symbol = false()}
{if $currency}
    {set locale = $currency.locale
         symbol = $currency.symbol}
{/if}

<strong>{"Order"|i18n("design/standard/shop")} n. {$order.order_nr}</strong>
<table width="100%" border="1" cellspacing="0" cellpadding="4">
    <tr>
        <th>
            {'Product'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'Count'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'Price inc. VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'Total price ex. VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'Total price inc. VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
    </tr>
    {if $order.product_items|count()}
        {foreach $order.product_items as $product_item sequence array( 'bglight', 'bgdark' ) as $style}
            <tr>
                <td style="vertical-align: middle;">
                    <p>
                        <a href="{concat('openpa_booking/view/sala_pubblica/',$product_item.item_object.contentobject_id)|ezurl(no, full)}">
                            Prenotazione {$product_item.item_object.contentobject_id} {$product_item.item_object.contentobject.data_map.sala.content.name|wash()}
                        </a>&nbsp;

                    </p>
                    <ul class="list list-unstyled">
                        <li>{$product_item.object_name}</li>
                        {if $product_item.item_object.contentobject.main_node.children_count}
                            {foreach $product_item.item_object.contentobject.main_node.children as $child}
                                <li>{$child.name|wash()}</li>
                            {/foreach}
                        {/if}
                        {if $product_item.item_object.contentobject.data_map.stuff.has_content}
                            {foreach $product_item.item_object.contentobject.data_map.stuff.content.relation_list as $stuff}
                                {if and(is_set($stuff.extra_fields.booking_status), $stuff.extra_fields.booking_status.identifier|eq('approved'))}
                                    <li>{fetch(content,object, hash(object_id, $stuff.contentobject_id)).name|wash()}</li>
                                {/if}
                            {/foreach}
                        {/if}
                    </ul>
                </td>
                <td style="vertical-align: middle;text-align: center;">
                    {$product_item.item_count}
                </td>
                <td style="vertical-align: middle;text-align: center;">
                    {$product_item.vat_value} %
                </td>
                <td style="vertical-align: middle;text-align: center;">
                    {$product_item.price_inc_vat|l10n( 'currency', $locale, $symbol )}
                </td>
                <td style="vertical-align: middle;text-align: center;">
                    {$product_item.total_price_ex_vat|l10n( 'currency', $locale, $symbol )}
                </td>
                <td style="vertical-align: middle;text-align: center;">
                    {$product_item.total_price_inc_vat|l10n( 'currency', $locale, $symbol )}
                </td>
            </tr>
        {/foreach}
    {/if}
</table>

<br/><br/>
<strong>{'Order summary'|i18n( 'design/ezwebin/shop/orderview' )}</strong>
<table width="100%" border="1" cellspacing="0" cellpadding="4">
    <tr>
        <th>
            {'Summary'|i18n( 'design/ezwebin/shop/orderview' )}:
        </th>
        <th>
            {'Total price ex. VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
        <th>
            {'Total price inc. VAT'|i18n( 'design/ezwebin/shop/orderview' )}
        </th>
    </tr>
    <tr class="bglight">
        <td>
            {'Subtotal of items'|i18n( 'design/ezwebin/shop/orderview' )}:
        </td>
        <td>
            {$order.product_total_ex_vat|l10n( 'currency', $locale, $symbol )}
        </td>
        <td>
            {$order.product_total_inc_vat|l10n( 'currency', $locale, $symbol )}
        </td>
    </tr>
    {if $order.order_items|count()}
        {foreach $order.order_items as $order_item sequence array( 'bglight', 'bgdark' ) as $style}
            <tr class="{$style}">
                <td>
                    {$order_item.description}:
                </td>
                <td>
                    {$order_item.price_ex_vat|l10n( 'currency', $locale, $symbol )}
                </td>
                <td>
                    {$order_item.price_inc_vat|l10n( 'currency', $locale, $symbol )}
                </td>
            </tr>
        {/foreach}
    {/if}
    <tr class="bgdark">
        <td>
            {'Order total'|i18n( 'design/ezwebin/shop/orderview' )}
        </td>
        <td>
            {$order.total_ex_vat|l10n( 'currency', $locale, $symbol )}
        </td>
        <td>
            {$order.total_inc_vat|l10n( 'currency', $locale, $symbol )}
        </td>
    </tr>
</table>

{undef $currency $locale $symbol}
