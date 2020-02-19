<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LocationProviderDBIPApi\LocationProvider;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\GeoIp2\LocationProvider\GeoIp2;
use Piwik\Plugins\LocationProviderDBIPApi\SystemSettings;
use Piwik\Plugins\UserCountry\LocationProvider;
use Piwik\Url;

include_once PIWIK_INCLUDE_PATH . '/plugins/LocationProviderDBIPApi/lib/dbip-client.class.php';

/**
 * Base type for all GeoIP 2 LocationProviders.
 *
 */
class DBIPApi extends LocationProvider
{
    const ID = 'dbipapi';
    const TITLE = 'db-ip.com API';
    const TEST_IP = '194.57.91.215';

    /**
     * Returns location information based on visitor information.
     *
     * @param array $info What this must contain depends on the specific provider
     *                    implementation. All providers require an 'ip' key mapped
     *                    to the visitor's IP address.
     * @return array|false
     */
    public function getLocation($info)
    {
        $ip = $this->getIpFromInfo($info);
        $apiKey = $this->getApiKey();

        if (empty($ip)) {
            return false;
        }

        $result = [];

        if (!empty($apiKey)) {
            \DBIP\APIKey::set($apiKey);
        }

        $addrInfo = \DBIP\Address::lookup($ip);

        $result[self::CONTINENT_CODE_KEY] = $addrInfo->continentCode;
        $result[self::CONTINENT_NAME_KEY] = $addrInfo->continentName;
        $result[self::COUNTRY_CODE_KEY] = $addrInfo->countryCode;
        $result[self::COUNTRY_NAME_KEY] = $addrInfo->countryName;
        $result[self::CITY_NAME_KEY] = $addrInfo->city;

        if (property_exists($addrInfo, 'longitude')) {
            $result[self::LATITUDE_KEY] = $addrInfo->latitude;
            $result[self::LONGITUDE_KEY] = $addrInfo->longitude;
        }

        if (property_exists($addrInfo, 'zipCode')) {
            $result[self::POSTAL_CODE_KEY] = $addrInfo->zipCode;
        }

        if (property_exists($addrInfo, 'isp')) {
            $result[self::ISP_KEY] = $addrInfo->isp;
        }

        if (property_exists($addrInfo, 'organization')) {
            $result[self::ORG_KEY] = $addrInfo->organization;
        }

        if (property_exists($addrInfo, 'stateProv')) {
            $result[self::REGION_NAME_KEY] = $addrInfo->stateProv;
            $result[self::REGION_CODE_KEY] = $this->determineRegionIsoCodeByNameAndCountryCode($addrInfo->stateProv, $addrInfo->countryCode);
        }

        return $result;
    }

    /**
     * Try to determine the ISO region code based on the region name and country code
     *
     * @param string $regionName
     * @param string $countryCode
     * @return string
     */
    protected function determineRegionIsoCodeByNameAndCountryCode($regionName, $countryCode)
    {
        $regionNames = GeoIp2::getRegionNames();

        if (empty($regionNames[$countryCode])) {
            return '';
        }

        foreach ($regionNames[$countryCode] as $isoCode => $name) {
            if (Common::mb_strtolower($name) === Common::mb_strtolower($regionName)) {
                return $isoCode;
            }
        }

        return '';
    }

    /**
     * Returns true if this provider is available for use, false if otherwise.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * Returns true if this provider is working, false if otherwise.
     *
     * @return bool
     */
    public function isWorking()
    {
        try {
            $testIp = self::TEST_IP;

            // get location using test IP and check that some information was returned
            $location = $this->getLocation(array('ip' => $testIp));
            $location = array_filter($location);
            $isResultCorrect = !empty($location);

            if (!$isResultCorrect) {
                $bind = array($testIp);
                return Piwik::translate('UserCountry_TestIPLocatorFailed', $bind);
            }

            return true;
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * Returns information about this location provider.
     *
     * @return array
     */
    public function getInfo()
    {
        $desc = $extraMessage = $installDocs = '';

        $apiKey = $this->getApiKey();

        if (!empty($apiKey)) {
            \DBIP\APIKey::set($apiKey);
        }

        $info = \DBIP\APIKey::info();

        $desc .= Piwik::translate('LocationProviderDBIPApi_ProviderDescription', [
            '<a rel="noreferrer"  target="_blank" href="https://db-ip.com/?refid=mtm">', '</a>'
        ]);
        $desc .= '<br>' . Piwik::translate('LocationProviderDBIPApi_EnsureLimitMatches');


        if (empty($apiKey)) {
            $configUrl = Url::getCurrentQueryStringWithParametersModified(array(
                'module' => 'CoreAdminHome', 'action' => 'generalSettings'
            ));

            $desc .= '<br><br>' . Piwik::translate('LocationProviderDBIPApi_QueriesLeftToday', $info->queriesLeft);
            $desc .= '<br><br>' . Piwik::translate('LocationProviderDBIPApi_SetAPIKey', [
                '<a href="' . $configUrl . '">', '</a>'
            ]);
        } else {
            $desc .= '<br><br>' . Piwik::translate('LocationProviderDBIPApi_QuotaLeftToday', [$info->queriesLeft, $info->queriesPerDay]);
        }

        $availableInfo = $this->getSupportedLocationInfo();
        $availableDatabaseTypes = [];

        if (isset($availableInfo[self::CITY_NAME_KEY]) && $availableInfo[self::CITY_NAME_KEY]) {
            $availableDatabaseTypes[] = Piwik::translate('UserCountry_City');
        }

        if (isset($availableInfo[self::REGION_CODE_KEY]) && $availableInfo[self::REGION_CODE_KEY]) {
            $availableDatabaseTypes[] = Piwik::translate('UserCountry_Region');
        }

        if (isset($availableInfo[self::COUNTRY_NAME_KEY]) && $availableInfo[self::COUNTRY_NAME_KEY]) {
            $availableDatabaseTypes[] = Piwik::translate('UserCountry_Country');
        }

        if (isset($availableInfo[self::ISP_KEY]) && $availableInfo[self::ISP_KEY]) {
            $availableDatabaseTypes[] = Piwik::translate('GeoIp2_ISPDatabase');
        }

        if (!empty($availableDatabaseTypes)) {
            $extraMessage .= '<strong>' . Piwik::translate('General_Note') . '</strong>:&nbsp;'
                . Piwik::translate('LocationProviderDBIPApi_ImplHasAccessTo') . ':&nbsp;<strong>'
                . implode(', ', $availableDatabaseTypes) . '</strong>.';
        }


        return [
            'id'            => self::ID,
            'title'         => self::TITLE,
            'description'   => $desc,
            'install_docs'  => $installDocs,
            'extra_message' => $extraMessage,
            'order'         => 15
        ];
    }

    /**
     * Returns an array mapping location result keys w/ bool values indicating whether
     * that information is supported by this provider. If it is not supported, that means
     * this provider either cannot get this information, or is not configured to get it.
     *
     * @return array eg. array(self::CONTINENT_CODE_KEY => true,
     *                         self::CONTINENT_NAME_KEY => true,
     *                         self::ORG_KEY => false)
     *               The result is not guaranteed to have keys for every type of location
     *               info.
     */
    public function getSupportedLocationInfo()
    {
        $apiKey = $this->getApiKey();

        $result = [];

        if (!empty($apiKey)) {
            \DBIP\APIKey::set($apiKey);
        }

        $addrInfo = \DBIP\Address::lookup(self::TEST_IP);

        $result[self::CONTINENT_CODE_KEY] = true;
        $result[self::CONTINENT_NAME_KEY] = true;
        $result[self::COUNTRY_CODE_KEY] = true;
        $result[self::COUNTRY_NAME_KEY] = true;
        $result[self::CITY_NAME_KEY] = true;

        if (property_exists($addrInfo, 'stateProv')) {
            $result[self::REGION_CODE_KEY] = true;
            $result[self::REGION_NAME_KEY] = true;
        }

        if (property_exists($addrInfo, 'longitude')) {
            $result[self::LATITUDE_KEY] = true;
            $result[self::LONGITUDE_KEY] = true;
        }

        if (property_exists($addrInfo, 'zipCode')) {
            $result[self::POSTAL_CODE_KEY] = true;
        }

        if (property_exists($addrInfo, 'isp')) {
            $result[self::ISP_KEY] = true;
        }

        if (property_exists($addrInfo, 'organization')) {
            $result[self::ORG_KEY] = true;
        }

        return $result;
    }

    /**
     * Returns an IP address from an array that was passed into getLocation. This
     * will return an IPv4 address or IPv6 address.
     *
     * @param  array $info Must have 'ip' key.
     * @return string|null
     */
    protected function getIpFromInfo($info)
    {
        $ip = \Matomo\Network\IP::fromStringIP($info['ip']);

        return $ip->toString();
    }

    protected function getApiKey()
    {
        $systemSettings = new SystemSettings();

        return $systemSettings->api_key->getValue();
    }
}
