<?php

/**
 * Shopware Paypal Plugin
 *
 * @category  Goldbek Solutions
 * @package   Shopware\Plugins\Frontend\CODTestPlugin
 * @copyright Copyright (c) Goldbek Solutions UG (http://www.goldbek-solutions.de)
 */
class Shopware_Plugins_Frontend_CODTestPlugin_Bootstrap 
    extends Shopware_Components_Plugin_Bootstrap
{
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true
        );
    }
 
    public function getLabel()
    {
        return 'COD Test-Plugin';
    }
 
    public function getVersion()
    {
        return '1.0.0';
    }
 
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'license' => 'commercial',
            'supplier' => 'Goldbek Solutions UG',
            'autor' => 'Goldbek Solutions UG',
            'description' => 'Dieses Plugin dient für verschiedene Testzwecke',
            'support' => 'info@goldbek-solutions.de',
            'copyright'   => 'Copyright © 2014, Goldbek Solutions UG',
            'link' => 'http://www.goldbek-solutions.de'
        );
    }

 
    public function install()
    {
        $this->createDatabase();
        $this->registerEvents();
        $this->registerCronJobs();

        return true;
    }

    public function uninstall()
    {
        $this->removeDatabase();

        return true;
    }

    private function removeDatabase()
    {
        $sql= "DROP TABLE IF EXISTS `slogans`";
        Shopware()->Db()->query($sql);
    }
    
    private function createDatabase()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `slogans` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `text` text COLLATE utf8_unicode_ci NOT NULL,
              `active` int(1) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
        ";
        Shopware()->Db()->query($sql);

        $sql = "
            INSERT INTO `slogans` (`id`, `text`, `active`) VALUES
            (1, 'Goldbek Cards!', 1),
            (2, 'Smarty is keine Schokolinse!', 0),
            (3, 'Ein Zend ist kein Geldstück!', 0);
        ";

        Shopware()->Db()->query($sql);
    }

    private function registerEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Detail',
            'onPostDispatchDetail'
        );
    }

    private function registerCronJobs()
    {
        $this->createCronJob(
            'SwagSloganOfTheDay',
            'SloganOfTheDayCron',
            86400,
            true
        );

        $this->subscribeEvent(
            'Shopware_CronJob_SloganOfTheDayCron',
            'onRunSloganCronJob'
        );
    }
    
    public function onPostDispatchDetail(Enlight_Event_EventArgs $arguments)
    {     

        /**@var $controller Shopware_Controllers_Frontend_Index*/
        $controller = $arguments->getSubject();

        /**
        * @var $request Zend_Controller_Request_Http
        */
        $request = $controller->Request();

        /**
        * @var $response Zend_Controller_Response_Http
        */
        $response = $controller->Response();

        /**
        * @var $view Enlight_View_Default
        */
        $view = $controller->View();

        //Check if there is a template and if an exception has occured
        if(!$request->isDispatched()||$response->isException()||!$view->hasTemplate()) {
           return;
        }
        //Add our plugin template directory to load our slogan extension.
        $view->addTemplateDir($this->Path() . 'Views/');

        $view->extendsTemplate('frontend/plugins/index.tpl');
        
        // Get Article Information
        $sArticle = $view->getAssign('sArticle');
        
        // Artikel: http://wiki.shopware.de/Einsteiger-Detailseiten-Reiter-Konfiguration_detail_1019.html
        $view->assign('sArticelInfo', "Artikel-Nr.: ".$sArticle['articleID']);
        //$view->assign('sArticelInfo', "Artikel-Nr.: ".json_encode($sArticle));
        //$view->assign('sArticelInfo', "Artikel: ".  serialize($sArticle));
        $view->assign('sDebugVariable', "Debug-Info Enlight_Event_EventArgs: ".$arguments->getName());
        //$view->assign('slogan', $this->getActiveSlogan());
    }

    private function getActiveSlogan()
    {
        $sql= "SELECT * FROM slogans WHERE active = ?";
        $slogan = Shopware()->Db()->fetchRow($sql, array(1));

        if (empty($slogan)) {
            $sql= "SELECT * FROM slogans";
            $slogan = Shopware()->Db()->fetchRow($sql);
        }

        return $slogan['text'];
    }    

    public function onRunSloganCronJob(Shopware_Components_Cron_CronJob $job)
    {
        //first we have to get the current active slogan
        $sql= "SELECT * FROM slogans WHERE active = ?";
        $activeSlogan = Shopware()->Db()->fetchRow($sql, array(1));

        //now we disable all slogans
        $sql = "UPDATE slogans SET active = ?";
        Shopware()->Db()->query($sql, array(0));

        //than we have to get the next slogan id
        $nextSloganId = $this->getNextSloganId($activeSlogan['id']);

        //now we can set the next slogan active
        $sql = "UPDATE slogans SET active = ? WHERE id = ?";
        Shopware()->Db()->query($sql, array(1, $nextSloganId));

        $sql= "SELECT * FROM slogans WHERE active = ?";
        $newSlogan = Shopware()->Db()->fetchRow($sql, array(1));

        echo "<br>";
        echo "Vorherige Slogan : " . $activeSlogan['text'];
        echo "<br>";
        echo "Aktiver   Slogan : " . $newSlogan['text'];
        echo "<br>";

        return true;
    }

    private function getNextSloganId($previousId)
    {
        //if no previous id passed, return the id of the first slogan
        if (empty($previousId)) {
            return $this->getFirstSloganId();
        }

        //get the next bigger slogan id
        $sql = "SELECT id FROM slogans WHERE id > ?";
        $nextId = Shopware()->Db()->fetchOne($sql, array($previousId));

        //check if the last slogan was active
        if (empty($nextId)) {

            //in this case return the id of the first slogan
            $nextId  = $this->getFirstSloganId();
        }

        return $nextId;
    }

    private function getFirstSloganId()
    {
        $sql = "SELECT id FROM slogans";
        return Shopware()->Db()->fetchOne($sql);
    }
    
    
}

?>