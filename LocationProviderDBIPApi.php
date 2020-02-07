<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LocationProviderDBIPApi;

use Piwik\Plugins\LocationProviderDBIPApi\LocationProvider\DBIPApi;
use Piwik\Plugins\UserCountry\LocationProvider;

/**
 *
 */
class LocationProviderDBIPApi extends \Piwik\Plugin
{
    public function isTrackerPlugin()
    {
        return true;
    }

    public function deactivate()
    {
        // switch to default provider if GeoIP2 provider was in use
        if (LocationProvider::getCurrentProvider() instanceof DBIPApi) {
            LocationProvider::setCurrentProvider(LocationProvider\DefaultProvider::ID);
        }
    }
}
