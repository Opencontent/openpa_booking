{* DO NOT EDIT THIS FILE! Use an override template instead. *}
{default attribute_base=ContentObjectAttribute}
{let matrix=$attribute.content}

{* Matrix. *}
{if count($matrix.rows.sequential)}
<table class="table table-striped" cellspacing="0">

<tr>
    <th class="tight">&nbsp;</th>
    {foreach $matrix.columns.sequential as $column_name}<th>{$column_name.name}</th>{/foreach}
</tr>

{foreach $matrix.rows.sequential as $row_index => $row}
<tr>

{* Remove. *}
<td>
    <input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_remove_{$row_index}" 
           class="ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" 
           type="checkbox" 
           name="{$attribute_base}_data_matrix_remove_{$attribute.id}[]" 
           value="{$row_index}" 
           title="{'Select row for removal.'|i18n( 'design/standard/content/datatype' )}" />
</td>

{* Custom columns. *}
{foreach $row.columns as $column_index => $column}
<td>
    {def $view = 'default'}
    
    {foreach $matrix.columns.sequential as $column_name}
       {if $column_name.index|eq($column_index)}
        {if $column_name.identifier|eq('vat')}
            {set $view = 'vat'}
        {elseif $column_name.identifier|eq('vat_type')}
            {set $view = 'vat_type'}
        {elseif $column_name.identifier|eq('description')}
            {set $view = 'textarea'}
        {/if}        
       {/if}
    {/foreach}

    {if $view|eq('default')}
        <input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_matrix_cell_{$row_index}_{$column_index}" 
               class="form-control ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" 
               type="text" 
               name="{$attribute_base}_ezmatrix_cell_{$attribute.id}[]" 
               value="{$column|wash( xhtml )}" />
    {elseif $view|eq('textarea')}
        <textarea id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_matrix_cell_{$row_index}_{$column_index}" 
                  class="form-control ezcc-{$attribute.object.content_class.identifier}"
                  name="{$attribute_base}_ezmatrix_cell_{$attribute.id}[]" 
                  cols="70" 
                  rows="2">{$column|wash( xhtml )}</textarea>
    {elseif $view|eq('vat')}
        <select id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_matrix_cell_{$row_index}_{$column_index}" 
               class="form-control ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" 
               name="{$attribute_base}_ezmatrix_cell_{$attribute.id}[]">
            <option value="0" {if or(eq($column,'0'),eq($column,''))}selected="selected"{/if}></option>
            <option value="1" {if eq($column,'1')}selected="selected"{/if}>{'Price inc. VAT'|i18n( 'design/standard/class/datatype' )}</option>
            <option value="2" {if eq($column,'2')}selected="selected"{/if}>{'Price ex. VAT'|i18n( 'design/standard/class/datatype' )}</option>
        </select>
    {elseif $view|eq('vat_type')}        
        <select class="form-control ezcc-{$attribute.object.content_class.identifier}"
                name="{$attribute_base}_ezmatrix_cell_{$attribute.id}[]">
            {foreach booking_vat_type_list() as $vat_type}
            <option value="{$vat_type.id}" {if eq( $vat_type.id, $column )}selected="selected"{/if}>
                {$vat_type.name|wash}{if $vat_type.is_dynamic|not}, {$vat_type.percentage}%{/if}
            </option>
            {/foreach}
        </select>
    {/if}
    {undef $view}
</td>
{/foreach}

</tr>
{/foreach}
</table>
{else}
<p>{'There are no rows in the matrix.'|i18n( 'design/standard/content/datatype' )}</p>
{/if}



{* Buttons. *}
<div class="row">
<div class="col-xs-3">
{if $matrix.rows.sequential}
<input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_remove_selected" 
       class="button btn btn-md btn-danger ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" 
       type="submit" 
       name="CustomActionButton[{$attribute.id}_remove_selected]" 
       value="{'Remove selected'|i18n( 'design/standard/content/datatype' )}" 
       title="{'Remove selected rows from the matrix.'|i18n( 'design/standard/content/datatype' )}" />
{else}
<input class="button-disabled btn btn-md" type="submit" name="CustomActionButton[{$attribute.id}_remove_selected]" value="{'Remove selected'|i18n( 'design/standard/content/datatype' )}" disabled="disabled" />
{/if}
&nbsp;&nbsp;
{let row_count=sub( 40, count( $matrix.rows.sequential ) ) index_var=0}
{if $row_count|lt( 1 )}
        {set row_count=0}
{/if}
</div>
<div class="col-xs-2 col-sm-offset-4">
<select id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_add_count"
        class="form-control matrix_cell ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}"
        name="{$attribute_base}_data_matrix_add_count_{$attribute.id}"
        title="{'Number of rows to add.'|i18n( 'design/standard/content/datatype' )}" >
    <option value="1">1</option>
    {section loop=$row_count}
        {set index_var=$index_var|inc}
        {delimiter modulo=5}
           <option value="{$index_var}">{$index_var}</option>
        {/delimiter}
   {/section}
</select>
</div>
<div class="col-xs-3">
<input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}_new_row" class="button btn btn-md btn-success  ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" type="submit" name="CustomActionButton[{$attribute.id}_new_row]" value="{'Add rows'|i18n('design/standard/content/datatype')}" title="{'Add new rows to the matrix.'|i18n( 'design/standard/content/datatype' )}" />
</div>
</div>
{/let}


{/let}
{/default}
