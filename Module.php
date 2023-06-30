<?php
/**
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DemoModeCalendarInitializerPlugin;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $aRequireModules = [
        'DemoModePlugin',
        'Calendar'
    ];
    protected $oDemoModePluginDecorator = null;

    protected $oCalendarDecorator = null;

    /***** private functions *****/

    public function init()
    {
        $oDemoModePluginDecorator = \Aurora\Modules\DemoModePlugin\Module::Decorator();
        $oCalendarDecorator = \Aurora\Modules\Calendar\Module::Decorator();

        if (!$oDemoModePluginDecorator || !$oCalendarDecorator) {
            return;
        }

        $this->oDemoModePluginDecorator = $oDemoModePluginDecorator;
        $this->oCalendarDecorator = $oCalendarDecorator;

        $this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'), 10);
    }

    public function onAfterLogin(&$aArgs, &$mResult)
    {
        $oSettings = $this->oDemoModePluginDecorator->GetSettings();
        $bDemoUser = isset($oSettings['IsDemoUser']) && !!$oSettings['IsDemoUser'] && isset($aArgs['NewDemoUser']) && $aArgs['NewDemoUser'];
        if ($bDemoUser) {
            $oUser = \Aurora\System\Api::getAuthenticatedUser();

            if ($oUser) {
                $sCalendarDisplayname = $this->oCalendarDecorator->i18N('CALENDAR_DEFAULT_NAME', null, null, $oUser->UUID);
                $aCalendar = $this->oCalendarDecorator->CreateCalendar($oUser->Id, $sCalendarDisplayname, '', \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_COLOR);
                if (is_array($aCalendar) && isset($aCalendar['Id'])) {
                    $this->populateData($aCalendar['Id'], 'events');
                }

                $sTaskDisplayname = $this->oCalendarDecorator->i18N('TASKS_DEFAULT_NAME', null, null, $oUser->UUID);
                $aTask = $this->oCalendarDecorator->CreateCalendar($oUser->Id, $sTaskDisplayname, '', \Afterlogic\DAV\Constants::TASKS_DEFAULT_COLOR);
                if (is_array($aTask) && isset($aTask['Id'])) {
                    $this->populateData($aTask['Id'], 'tasks');
                }
            }
        }
    }

    protected function populateData($sCalendarId, $sType)
    {
        $iErrors = 0;
        $sResourceDir = __Dir__.'/content/';

        $oUser = \Aurora\System\Api::getAuthenticatedUser();

        $aFiles = scandir($sResourceDir.$sType.'/');
        $i = 0;
        foreach ($aFiles as $sFileName) {
            if ($sFileName !== '.' && $sFileName !== '..') {
                $sData = \file_get_contents($sResourceDir.$sType.'/'.$sFileName);
                $sUUID = \Sabre\DAV\UUIDUtil::getUUID();
                $sDate = gmdate('Ymd', time() + 60 * 60 * 24 * $i);

                $sData = str_replace('%UID%', $sUUID, $sData);
                $sData = str_replace('%DATE%', $sDate, $sData);

                $oResult = $this->oCalendarDecorator->CreateEventFromData($oUser->Id, $sCalendarId, $sUUID, $sData);
                $i++;

                if (isset($oResult['Error'])) {
                    $iErrors++;
                }
            }
        }

        return $iErrors > 0;
    }

    /***** private functions *****/
}
