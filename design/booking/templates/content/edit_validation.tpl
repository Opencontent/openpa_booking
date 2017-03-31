{section show=$validation.processed}
{section show=or($validation.attributes,$validation.placement,$validation.custom_rules)}

<div class="alert alert-danger text-center">
    {section show=or(and($validation.attributes,$validation.placement),$validation.custom_rules)}
    <h2>{"Validation failed"|i18n("design/standard/content/edit")}</h2>
    {section-else}
    {section show=$validation.attributes}
        <h2>{"Input did not validate"|i18n("design/standard/content/edit")}</h2>
        {section-else}
            <h2>{"Location did not validate"|i18n("design/standard/content/edit")}</h2>
        {/section}
    {/section}
    <ul class="list list-unstyled">
        {section name=UnvalidatedPlacements loop=$validation.placement show=$validation.placement}
            <li>{$:item.text}</li>
        {/section}
        {section name=UnvalidatedAttributes loop=$validation.attributes show=$validation.attributes}
            <li>{$:item.name|wash}: {$:item.description}</li>
        {/section}
        {section name=UnvalidatedCustomRules loop=$validation.custom_rules show=$validation.custom_rules}
            <li>{$:item.text}</li>
        {/section}
    </ul>
</div>

{section-else}
    {section show=$validation_log}
        <div class="alert alert-danger text-center">
            <h2>{"Input was partially stored"|i18n("design/standard/content/edit")}</h2>
            {section name=ValidationLog loop=$validation_log}
                <h4>{$:item.name|wash}:</h4>
                <ul class="list list-unstyled">
                    {section name=LogMessage loop=$:item.description}
                        <li>{$:item}</li>
                    {/section}
                </ul>
            {/section}
        </div>
        {section-else}
            <div class="alert alert-success text-center">
                <h2>{"Input was stored successfully"|i18n("design/standard/content/edit")}</h2>
            </div>
        {/section}
    {/section}
{/section}
