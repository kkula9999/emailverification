<?php
   if (!defined('_PS_VERSION_')) {
       exit;
   }

   class EmailVerification extends Module
   {
       public function __construct()
       {
           $this->name = 'emailverification';
           $this->tab = 'front_office_features';
           $this->version = '1.0.0';
           $this->author = 'Your Name';
           $this->need_instance = 0;
           parent::__construct();
           PrestaShopLogger::addLog('EmailVerification module instantiated', 1, null, 'EmailVerification', 1);
           $this->displayName = $this->l('Email Verification');
           $this->description = $this->l('Adds email verification to customer registration.');
           $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
       }

       public function install()
       {
           PrestaShopLogger::addLog('EmailVerification install method called', 1, null, 'EmailVerification', 1);
           return parent::install() &&
                  $this->registerHook('actionCustomerAccountAdd') &&
                  $this->registerHook('actionValidateCustomer') &&
                  $this->registerHook('actionCustomerRegisterSubmit') &&
                  Db::getInstance()->execute('
                      CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'email_verification` (
                          `id_customer` INT(10) UNSIGNED NOT NULL,
                          `token` VARCHAR(255) NOT NULL,
                          `date_add` DATETIME NOT NULL,
                          PRIMARY KEY (`id_customer`)
                      ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
                  );
       }

       public function uninstall()
       {
           PrestaShopLogger::addLog('EmailVerification uninstall method called', 1, null, 'EmailVerification', 1);
           return parent::uninstall() &&
                  Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'email_verification`');
       }

       public function hookActionCustomerRegisterSubmit($params)
       {
           PrestaShopLogger::addLog('actionCustomerRegisterSubmit hook executed', 1, null, 'EmailVerification', 1);
       }

       public function hookActionCustomerAccountAdd($params)
       {
           PrestaShopLogger::addLog('actionCustomerAccountAdd hook executed', 1, null, 'EmailVerification', 1);
           $customer = $params['newCustomer'];
           $token = bin2hex(random_bytes(16));

           Db::getInstance()->insert('email_verification', [
               'id_customer' => (int)$customer->id,
               'token' => pSQL($token),
               'date_add' => date('Y-m-d H:i:s'),
           ]);

           $templateVars = [
               '{firstname}' => $customer->firstname,
               '{lastname}' => $customer->lastname,
               '{verification_link}' => $this->context->link->getModuleLink($this->name, 'verify', ['token' => $token]),
           ];

           Mail::Send(
               (int)$this->context->language->id,
               'verification_email',
               $this->l('Verify Your Email Address'),
               $templateVars,
               $customer->email,
               $customer->firstname . ' ' . $customer->lastname,
               null,
               null,
               null,
               null,
               _PS_MODULE_DIR_ . 'emailverification/mails/'
           );

           $customer->active = 0;
           $customer->update();
           PrestaShopLogger::addLog('Customer ' . (int)$customer->id . ' set to active=0 and verification email sent', 1, null, 'EmailVerification', 1);
       }

       public function hookActionValidateCustomer($params)
       {
           PrestaShopLogger::addLog('actionValidateCustomer hook executed', 1, null, 'EmailVerification', 1);
           $customer = $params['customer'];
           $verified = Db::getInstance()->getValue('
               SELECT COUNT(*)
               FROM `' . _DB_PREFIX_ . 'email_verification`
               WHERE `id_customer` = ' . (int)$customer->id . '
               AND `token` = ""
           ');

           if (!$verified) {
               throw new PrestaShopException($this->l('Please verify your email address before logging in.'));
           }
       }
   }