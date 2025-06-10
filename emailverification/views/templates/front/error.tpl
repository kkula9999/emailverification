{extends file='page.tpl'}
{block name='page_content'}
    <div class="alert alert-danger">
        {l s='Invalid or expired verification link.' d='Modules.Emailverification'}
    </div>
    <a href="{$urls.pages.authentication}" class="btn btn-primary">{l s='Back to Login' d='Shop.Theme.Actions'}</a>
{/block}