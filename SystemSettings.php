<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LocationProviderDBIPApi;

use Piwik\Piwik;
use Piwik\Plugins\UserCountry\UserCountry;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for UserCountry.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $api_key;

    protected function init()
    {
        $this->title = Piwik::translate('LocationProviderDBIPApi_ConfigureApiAccess');

        $geoIpAdminEnabled = UserCountry::isGeoLocationAdminEnabled();

        $this->api_key = $this->makeSetting('dbipapikey', false, FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('LocationProviderDBIPApi_ApiKey');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });

        $this->api_key->setIsWritableByCurrentUser($geoIpAdminEnabled);
    }
}