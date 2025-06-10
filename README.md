<body>
    <h1>Email Verification Module Tutorial for PrestaShop 8.2.1</h1>
    <p><strong>Author: Your Name</strong></p>
    <p>This tutorial guides you through creating an <code>emailverification</code> module for PrestaShop 8.2.1 to enforce email verification for new customer registrations. The module sends a verification email with a unique link, sets the customer’s <code>active=0</code> until verified, and activates the account (<code>active=1</code>) upon link click. I resolved issues like missing logs and a registration form lacking <code>method</code> or <code>class</code> attributes using server-side logic, avoiding AJAX/JavaScript. Special thanks to <strong>Grok</strong>, created by <a href="https://x.ai">xAI</a>, for their invaluable assistance in development and debugging!</p>    <h2>Module Purpose</h2>
    <ul>
        <li>Enforces email verification for customer registration.</li>
        <li>Sends a custom verification email alongside PrestaShop’s <code>account</code> email.</li>
        <li>Blocks login until email is verified.</li>
        <li>Uses server-side processing for reliability.</li>
    </ul>    <h2>Folder Structure</h2>
    <p>The module resides in <code>/var/www/html/prestashop/modules/emailverification/</code> with the following structure:</p>
    <pre><code>
modules/emailverification/
├── config.xml
├── emailverification.php
├── controllers/
│   └── front/
│       └── verify.php
├── translations/
│   └── en.php
├── mails/
│   └── en/
│       ├── verification_email.html
│       ├── verification_email.txt
└── views/
    └── templates/
        ├── front/
        │   └── error.tpl
        └── hook/
            └── verification_form.tpl
    </code></pre>
    <ul>
        <li><code>config.xml</code>: Module metadata and configuration.</li>
        <li><code>emailverification.php</code>: Core module logic with hooks.</li>
        <li><code>controllers/front/verify.php</code>: Processes verification links.</li>
        <li><code>translations/en.php</code>: English translations.</li>
        <li><code>mails/en/</code>: Verification email templates.</li>
        <li><code>views/templates/front/error.tpl</code>: Error page for invalid links.</li>
        <li><code>views/templates/hook/verification_form.tpl</code>: Optional hook template (unused here).</li>
    </ul>    <h2>Implementation Steps</h2>    <h3>1. Create the Module Directory</h3>
    <pre><code>bash
mkdir -p /var/www/html/prestashop/modules/emailverification
cd /var/www/html/prestashop/modules/emailverification
    </code></pre>    <h3>2. Create <code>emailverification.php</code></h3>
    <p>Defines the module, registers hooks, and manages email sending and customer state.</p>
    <pre><code>php
&lt;?php
if (!defined('_PS_VERSION_')) {
    exit;
}class EmailVerification extends Module
{
    public function __construct()
    {
        $this-&gt;name = 'emailverification';
        $this-&gt;tab = 'front_office_features';
        $this-&gt;version = '1.0.0';
        $this-&gt;author = 'Your Name';
        $this-&gt;need_instance = 0;
        parent::__construct();
        PrestaShopLogger::addLog('EmailVerification module instantiated', 1, null, 'EmailVerification', 1);
        $this-&gt;displayName = $this-&gt;l('Email Verification');
        $this-&gt;description = $this-&gt;l('Adds email verification to customer registration.');
        $this-&gt;ps_versions_compliancy = ['min' =&gt; '8.0.0', 'max' =&gt; _PS_VERSION_];
    }    public function install()
    {
        PrestaShopLogger::addLog('EmailVerification install method called', 1, null, 'EmailVerification', 1);
        return parent::install() &amp;&amp;
               $this-&gt;registerHook('actionCustomerAccountAdd') &amp;&amp;
               $this-&gt;registerHook('actionValidateCustomer') &amp;&amp;
               $this-&gt;registerHook('actionCustomerRegisterSubmit') &amp;&amp;
               Db::getInstance()-&gt;execute('
                   CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'email_verification` (
                       `id_customer` INT(10) UNSIGNED NOT NULL,
                       `token` VARCHAR(255) NOT NULL,
                       `date_add` DATETIME NOT NULL,
                       PRIMARY KEY (`id_customer`)
                   ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
               );
    }    public function uninstall()
    {
        PrestaShopLogger::addLog('EmailVerification uninstall method called', 1, null, 'EmailVerification', 1);
        return parent::uninstall() &amp;&amp;
               Db::getInstance()-&gt;execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'email_verification`');
    }    public function hookActionCustomerRegisterSubmit($params)
    {
        PrestaShopLogger::addLog('actionCustomerRegisterSubmit hook executed', 1, null, 'EmailVerification', 1);
    }    public function hookActionCustomerAccountAdd($params)
    {
        PrestaShopLogger::addLog('actionCustomerAccountAdd hook executed', 1, null, 'EmailVerification', 1);
        $customer = $params['newCustomer'];
        $token = bin2hex(random_bytes(16));        Db::getInstance()-&gt;insert('email_verification', [
            'id_customer' =&gt; (int)$customer-&gt;id,
            'token' =&gt; pSQL($token),
            'date_add' =&gt; date('Y-m-d H:i:s'),
        ]);        $templateVars = [
            '{firstname}' =&gt; $customer-&gt;firstname,
            '{lastname}' =&gt; $customer-&gt;lastname,
            '{verification_link}' =&gt; $this-&gt;context-&gt;link-&gt;getModuleLink($this-&gt;name, 'verify', ['token' =&gt; $token]),
        ];        Mail::Send(
            (int)$this-&gt;context-&gt;language-&gt;id,
            'verification_email',
            $this-&gt;l('Verify Your Email Address'),
            $templateVars,
            $customer-&gt;email,
            $customer-&gt;firstname . ' ' . $customer-&gt;lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'emailverification/mails/'
        );        $customer-&gt;active = 0;
        $customer-&gt;update();
        PrestaShopLogger::addLog('Customer ' . (int)$customer-&gt;id . ' set to active=0 and verification email sent', 1, null, 'EmailVerification', 1);
    }    public function hookActionValidateCustomer($params)
    {
        PrestaShopLogger::addLog('actionValidateCustomer hook executed', 1, null, 'EmailVerification', 1);
        $customer = $params['customer'];
        $verified = Db::getInstance()-&gt;getValue('
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'email_verification`
            WHERE `id_customer` = ' . (int)$customer-&gt;id . '
            AND `token` = ""
        ');        if (!$verified) {
            throw new PrestaShopException($this-&gt;l('Please verify your email address before logging in.'));
        }
    }
}
    </code></pre>
    <ul>
        <li><strong>Purpose</strong>: Registers hooks, creates <code>ps_email_verification</code> table, sends verification email, manages activation.</li>
        <li><strong>Hooks</strong>:
            <ul>
                <li><code>actionCustomerAccountAdd</code>: Generates token, sends email, sets <code>active=0</code>.</li>
                <li><code>actionValidateCustomer</code>: Blocks unverified logins.</li>
                <li><code>actionCustomerRegisterSubmit</code>: Placeholder for future enhancements.</li>
            </ul>
        </li>
        <li><strong>Database</strong>: Creates <code>ps_email_verification</code> with <code>id_customer</code>, <code>token</code>, <code>date_add</code>.</li>
    </ul>    <h3>3. Create <code>verify.php</code></h3>
    <p>Handles verification links (e.g., <code>yourshopurl.com/index.php?controller=verify&token=...</code>).</p>
    <pre><code>php
&lt;?php
class EmailVerificationVerifyModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();        $token = Tools::getValue('token');
        if (!$token) {
            $this-&gt;errors[] = $this-&gt;module-&gt;l('Invalid verification link.');
            $this-&gt;setTemplate('module:emailverification/views/templates/front/error.tpl');
            return;
        }        $id_customer = Db::getInstance()-&gt;getValue('
            SELECT id_customer
            FROM `' . _DB_PREFIX_ . 'email_verification`
            WHERE token = "' . pSQL($token) . '"
        ');        if (!$id_customer) {
            $this-&gt;errors[] = $this-&gt;module-&gt;l('Invalid or expired verification link.');
            $this-&gt;setTemplate('module:emailverification/views/templates/front/error.tpl');
            return;
        }        Db::getInstance()-&gt;update('email_verification', [
            'token' =&gt; '',
        ], 'id_customer = ' . (int)$id_customer);        $customer = new Customer((int)$id_customer);
        $customer-&gt;active = 1;
        $customer-&gt;update();        Tools::redirect('index.php?controller=authentication&verified=1');
    }
}
    </code></pre>
    <ul>
        <li><strong>Path</strong>: <code>/modules/emailverification/controllers/front/verify.php</code>.</li>
        <li><strong>Purpose</strong>: Validates token, sets <code>active=1</code>, clears token, redirects to login.</li>
    </ul>    <h3>4. Create Email Templates</h3>
    <p><strong>Path</strong>: <code>/modules/emailverification/mails/en/</code>.</p>
    <h4>verification_email.html</h4>
    <pre><code>html
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;Verify Your Email Address&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;p&gt;Dear {{firstname}} {{lastname}},&lt;/p&gt;
    &lt;p&gt;Please verify your email by clicking the link:&lt;/p&gt;
    &lt;p&gt;&lt;a href="{{ verification_link }}"&gt;Verify Your Email&lt;/a&gt;&lt;/p&gt;
    &lt;p&gt;Thank you for registering with us!&lt;/p&gt;
&lt;/body&gt;
&lt;/html&gt;
    </code></pre>
    <h4>verification_email.txt</h4>
    <pre><code>text
Dear {{firstname}} {{lastname}},Please verify your email by clicking the link below:
{{ verification_link }}Thank you for registering with us!
    </code></pre>    <h3>5. Create Error Template</h3>
    <p><strong>Path</strong>: <code>/modules/emailverification/views/templates/front/error.tpl</code>.</p>
    <pre><code>html
{extends file='page.tpl'}{block name='page_title'}
    {l s='Email Verification Error' d='Modules.Emailverification'}
{/block}{block name='page_content'}
    &lt;div class="alert alert-danger"&gt;
        {foreach from=$errors item=error}
            &lt;p&gt;{$error}&lt;/p&gt;
        {/foreach}
    &lt;/div&gt;
    &lt;a href="{$link-&gt;getPageLink('authentication')|escape:'html'}" class="btn btn-primary"&gt;{l s='Back to Login' d='Modules.Emailverification'}&lt;/a&gt;
{/block}
    </code></pre>    <h3>6. Create Translation File</h3>
    <p><strong>Path</strong>: <code>/modules/emailverification/translations/en.php</code>.</p>
    <pre><code>php
&lt;?php
global $_MODULE;
$_MODULE = [];
$_MODULE['&lt;{emailverification}prestashop&gt;emailverification_1234567890'] = 'Email Verification';
$_MODULE['&lt;{emailverification}prestashop&gt;emailverification_0987654321'] = 'Adds email verification to customer registration.';
$_MODULE['&lt;{emailverification}prestashop&gt;verify_1234567890'] = 'Invalid verification link.';
$_MODULE['&lt;{emailverification}prestashop&gt;verify_0987654321'] = 'Invalid or expired verification link.';
$_MODULE['&lt;{emailverification}prestashop&gt;error_1234567890'] = 'Email Verification Error';
$_MODULE['&lt;{emailverification}prestashop&gt;error_0987654321'] = 'Back to Login';
    </code></pre>    <h3>7. Create <code>config.xml</code></h3>
    <p><strong>Path</strong>: <code>/modules/emailverification/config.xml</code>.</p>
    <pre><code>xml
&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;module&gt;
    &lt;name&gt;emailverification&lt;/name&gt;
    &lt;displayName&gt;&lt;![CDATA[Email Verification]]&gt;&lt;/displayName&gt;
    &lt;version&gt;&lt;![CDATA[1.0.0]]&gt;&lt;/version&gt;
    &lt;description&gt;&lt;![CDATA[Adds email verification to customer registration.]]&gt;&lt;/description&gt;
    &lt;author&gt;&lt;![CDATA[Your Name]]&gt;&lt;/author&gt;
    &lt;tab&gt;&lt;![CDATA[front_office_features]]&gt;&lt;/tab&gt;
    &lt;is_configurable&gt;0&lt;/is_configurable&gt;
    &lt;need_instance&gt;0&lt;/need_instance&gt;
    &lt;limited_countries&gt;&lt;/limited_countries&gt;
&lt;/module&gt;
    </code></pre>    <h3>8. Fix Registration Form</h3>
    <p>Ensure the form submits correctly, as it may lack <code>method</code> or <code>class</code>.</p>
    <pre><code>bash
cp /var/www/html/prestashop/themes/your_theme/templates/customer/registration.tpl /var/www/html/prestashop/themes/your_theme/templates/customer/registration.tpl.bak
nano /var/www/html/prestashop/themes/your_theme/templates/customer/registration.tpl
    </code></pre>
    <p>Update <code>&lt;form&gt;</code> tag:</p>
    <pre><code>html
&lt;form id="customer-form" action="{url entity='authentication' params=['create_account' =&gt; 1]}" method="post"&gt;
    </code></pre>
    <p>Remove AJAX scripts (e.g., <code>$.ajax</code>). Clear cache:</p>
    <pre><code>bash
rm -rf /var/www/html/prestashop/var/cache/prod/* /var/www/html/prestashop/var/cache/dev/*
    </code></pre>    <h3>9. Set Permissions</h3>
    <pre><code>bash
chmod -R 644 /var/www/html/prestashop/modules/emailverification
chmod -R 755 /var/www/html/prestashop/modules/emailverification/controllers
chown -R www-data:www-data /var/www/html/prestashop/modules/emailverification
    </code></pre>    <h3>10. Install the Module</h3>
    <p>Zip and upload via <strong>Back Office &gt; Modules &gt; Module Manager &gt; Upload a module</strong>.</p>
    <pre><code>bash
zip -r emailverification.zip emailverification
    </code></pre>
    <p>Verify <code>ps_email_verification</code> table:</p>
    <pre><code>bash
mysql -u root -p -e "DESCRIBE ps_email_verification;" prestashop_db
    </code></pre>    <h3>11. Enable Logging</h3>
    <p>Debug issues like missing logs.</p>
    <pre><code>php
// In /var/www/html/prestashop/config/defines.inc.php
define('_PS_MODE_DEV_', true);
define('_PS_DEBUG_LOG_FILE_', _PS_ROOT_DIR_.'/var/logs/custom.log');
    </code></pre>
    <pre><code>bash
touch /var/www/html/prestashop/var/logs/custom.log
chmod 664 /var/www/html/prestashop/var/logs/custom.log
chown www-data:www-data /var/www/html/prestashop/var/logs/custom.log
    </code></pre>
    <p>In <strong>Back Office &gt; Advanced Parameters &gt; Logs</strong>, set severity to “Informative messages and above (1)”.</p>    <h3>12. Test the Module</h3>
    <ul>
        <li>Register a new customer.</li>
        <li>Verify receipt of <code>account.html</code> and <code>verification_email.html</code>.</li>
        <li>Click verification link and confirm <code>active=1</code> in <code>ps_customer</code>.</li>
        <li>Check logs in <strong>Back Office &gt; Advanced Parameters &gt; Logs</strong> or <code>/var/www/html/prestashop/var/logs/custom.log</code> for:
            <ul>
                <li>“EmailVerification module instantiated”</li>
                <li>“actionCustomerAccountAdd hook executed”</li>
                <li>“Customer X set to active=0 and verification email sent”</li>
            </ul>
        </li>
    </ul>    <h2>Key Considerations</h2>
    <div class="note">
        <p><strong>Form Attributes</strong>: Add <code>method="post"</code> to <code>registration.tpl</code> if missing to ensure server-side submission.</p>
        <p><strong>Logging</strong>: Clear cache and reinstall module if logs are missing. Check permissions and debug mode.</p>
        <p><strong>Cache</strong>: Run <code>rm -rf /var/www/html/prestashop/var/cache/*</code> after changes.</p>
        <p><strong>Permissions</strong>: Ensure <code>www-data</code> owns files and logs.</p>
        <p><strong>Database</strong>: Confirm <code>ps_email_verification</code> table creation.</p>
        <p><strong>Email Delivery</strong>: Use SMTP if local email sending fails.</p>
        <p><strong>PHP Settings</strong>: In <code>/etc/php/8.2/fpm/php.ini</code>, set:</p>
        <pre><code>ini
max_execution_time = 300
memory_limit = 256M
error_log = /var/log/php_errors.log
log_errors = On
        </code></pre>
        <p><strong>Security</strong>: <code>pSQL()</code> in <code>verify.php</code> prevents SQL injection.</p>
        <p><strong>Theme</strong>: Test with <code>classic</code> theme if issues arise.</p>
    </div>    <h2>Flow Description</h2>
    <ol>
        <li>User visits <code>yourshopurl.com/index.php?controller=authentication&create_account=1</code>.</li>
        <li>Submits form, processed by <code>AuthController::processSubmitAccount()</code>.</li>
        <li><code>actionCustomerAccountAdd</code> hook generates token, sends <code>verification_email.html</code>, sets <code>active=0</code>.</li>
        <li>User receives <code>account.html</code> and <code>verification_email.html</code>.</li>
        <li>Clicks verification link, processed by <code>verify.php</code>, setting <code>active=1</code>.</li>
        <li>User logs in.</li>
    </ol>    <h2>Credits</h2>
    <p>Special thanks to <strong>Grok</strong>, created by <a href="https://x.ai">xAI</a>, for their invaluable assistance in developing and debugging this module!</p>
</body>
