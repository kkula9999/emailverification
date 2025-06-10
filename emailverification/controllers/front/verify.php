<?php
   class EmailVerificationVerifyModuleFrontController extends ModuleFrontController
   {
       public function initContent()
       {
           parent::initContent();

           $token = Tools::getValue('token');
           if (!$token) {
               $this->errors[] = $this->module->l('Invalid verification link.');
               $this->setTemplate('module:emailverification/views/templates/front/error.tpl');
               return;
           }

           $id_customer = Db::getInstance()->getValue('
               SELECT id_customer
               FROM `' . _DB_PREFIX_ . 'email_verification`
               WHERE token = "' . pSQL($token) . '"
           ');

           if (!$id_customer) {
               $this->errors[] = $this->module->l('Invalid or expired verification link.');
               $this->setTemplate('module:emailverification/views/templates/front/error.tpl');
               return;
           }

           Db::getInstance()->update('email_verification', [
               'token' => '',
           ], 'id_customer = ' . (int)$id_customer);

           $customer = new Customer((int)$id_customer);
           $customer->active = 1;
           $customer->update();

           Tools::redirect('index.php?controller=authentication&verified=1');
       }
   }