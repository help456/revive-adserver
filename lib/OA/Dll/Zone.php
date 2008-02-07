<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                                               |
| =======${RELEASE_MAJOR_MINOR_DOUBLE_UNDERLINE}                                                                |
|                                                                           |
| Copyright (c) 2003-2008 m3 Media Services Ltd                             |
| For contact details, see: http://www.openx.org/                           |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

/**
 * @package    OpenXDll
 * @author     Ivan Klishch <iklishch@lohika.com>
 *
 */

// Require the following classes:
require_once MAX_PATH . '/lib/OA/Dll.php';
require_once MAX_PATH . '/lib/OA/Dll/ZoneInfo.php';
require_once MAX_PATH . '/lib/OA/Dal/Statistics/Zone.php';


/**
 * The OA_Dll_Zone class extends the base OA_Dll class.
 *
 */

class OA_Dll_Zone extends OA_Dll
{
    /**
     * This method sets ZoneInfo from a data array.
     *
     * @access private
     *
     * @param OA_Dll_ZoneInfo &$oZone
     * @param array $zoneData
     *
     * @return boolean
     */
    function _setZoneDataFromArray(&$oZone, $zoneData)
    {
        $zoneData['publisherId'] = $zoneData['affiliateid'];
        $zoneData['zoneId']      = $zoneData['zoneid'];
        $zoneData['type']        = $zoneData['delivery'];
        $zoneData['zoneName']    = $zoneData['zonename'];

        $oZone->readDataFromArray($zoneData);
        return  true;
    }

    /**
     * This method validates the zone type.
     * Types: banner=0, interstitial=1, popup=2, text=3, email=4
     *
     * @access private
     *
     * @param string $type
     *
     * @return boolean
     *
     */
    function _validateZoneType($type)
    {

        $arType = array(0, 1, 2, 3, 4);

        if (!isset($type) || in_array($type, $arType)) {
            $this->raiseError("Zone type is wrong!");
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method performs data validation for a zone, for example to check
     * that an email address is an email address. Where necessary, the method connects
     * to the OA_Dal to obtain information for other business validations.
     *
     * @access private
     *
     * @param OA_Dll_ZoneInfo $oZone
     *
     * @return boolean
     *
     */
    function _validate(&$oZone)
    {
        if (!$this->_validateZoneType($oZone->type) ||
            !$this->checkStructureNotRequiredStringField($oZone, 'zoneName', 245) ||
            !$this->checkStructureNotRequiredIntegerField($oZone, 'width') ||
            !$this->checkStructureNotRequiredIntegerField($oZone, 'height')) {

            return false;
        }

        if (isset($oZone->zoneId)) {
            // When modifying a zone, check correct field types are used and the zoneID exists.
            if (!$this->checkStructureRequiredIntegerField($oZone, 'zoneId') ||
                !$this->checkStructureNotRequiredIntegerField($oZone, 'publisherId') ||
                !$this->checkIdExistence('zones', $oZone->zoneId)) {
                return false;
            }
        } else {
            // When adding a zone, check that the required field 'publisherId' is correct.
            if (!$this->checkStructureRequiredIntegerField($oZone, 'publisherId')) {
                return false;
            }
        }

        if (isset($oZone->publisherId) &&
            !$this->checkIdExistence('affiliates', $oZone->publisherId)) {
            return false;
        }


        return true;
    }

    /**
     * This method performs data validation for statistics methods(zoneId, date).
     *
     * @access private
     *
     * @param integer  $zoneId
     * @param date     $oStartDate
     * @param date     $oEndDate
     *
     * @return boolean
     *
     */
    function _validateForStatistics($zoneId, $oStartDate, $oEndDate)
    {
        if (!$this->checkIdExistence('zones', $zoneId) ||
            !$this->checkDateOrder($oStartDate, $oEndDate)) {

            return false;
        } else {
            return true;
        }
    }

    /**
     * This function calls a method in the OA_Dll class which checks permissions.
     *
     * @access public
     *
     * @param integer $advertiserId  Zone ID
     *
     * @return boolean  False if access denied and true if allowed.
     */
    function checkStatisticsPermissions($zoneId)
    {
       if (!$this->checkPermissions($this->aAllowTraffickerAndAbovePerm, 'zones', $zoneId))
       {
           return false;
       } else {
           return true;
       }
    }

    /**
     * This method modifies an existing banner. Undefined fields do not change
     * and defined fields with a NULL value also remain unchanged.
     *
     * @access public
     *
     * @param OA_Dll_ZoneInfo &$oZone <br />
     *          <b>For adding</b><br />
     *          <b>Required properties:</b> publisherId<br />
     *          <b>Optional properties:</b> zoneName, type, width, height<br />
     *
     *          <b>For modify</b><br />
     *          <b>Required properties:</b> zoneId<br />
     *          <b>Optional properties:</b> publisherId, zoneName, type, width, height<br />
     *
     * @return success boolean True if the operation was successful
     *
     */
    function modify(&$oZone)
    {
        if (!isset($oZone->zoneId)) {
            // Add
            $oZone->setDefaultForAdd();
            if (!$this->checkPermissions($this->aAllowTraffickerAndAbovePerm,
                'affiliates', $oZone->publisherId, OA_PERM_ZONE_ADD))
            {
                return false;
            }
        } else {
            // Edit
            if (!$this->checkPermissions(
                $this->aAllowTraffickerAndAbovePerm,
                'zones', $oZone->zoneId, OA_PERM_ZONE_EDIT))
            {
                return false;
            }
        }

        $zoneData = (array) $oZone;

        // Name
        $zoneData['zonename']    = $oZone->zoneName;
        $zoneData['affiliateid'] = $oZone->publisherId;
        $zoneData['delivery']    = $oZone->type;
        $zoneData['width']       = $oZone->width;
        $zoneData['heitht']      = $oZone->heitht;

        if ($this->_validate($oZone)) {
            $doZone = OA_Dal::factoryDO('zones');
            if (!isset($zoneData['zoneId'])) {
                $doZone->setFrom($zoneData);
                $oZone->zoneId = $doZone->insert();

            } else {
                $doZone->get($zoneData['zoneId']);
                $doZone->setFrom($zoneData);
                $doZone->update();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method deletes an existing zone.
     *
     * @access public
     *
     * @param integer $zoneId The ID of the zone to delete
     *
     * @return boolean True if the operation was successful
     *
     */
    function delete($zoneId)
    {
        if (!$this->checkPermissions($this->aAllowTraffickerAndAbovePerm, 'zones', $zoneId)) {
            return false;
        }

        if (!$this->checkIdExistence('zones', $zoneId)) {
            return false;
        } else {
            $doZone = OA_Dal::factoryDO('zones');
            $doZone->zoneid = $zoneId;
            $result = $doZone->delete();
        }

        if ($result) {
            return true;
        } else {
            $this->raiseError('Unknown zoneId Error');
            return false;
        }
    }

    /**
     * This method returns ZoneInfo for a specified zone.
     *
     * @access public
     *
     * @param int $zoneId
     * @param OA_Dll_ZoneInfo &$oZone
     *
     * @return boolean
     */
    function getZone($zoneId, &$oZone)
    {
        if ($this->checkIdExistence('zones', $zoneId)) {
            if (!$this->checkPermissions(null, 'zones', $zoneId)) {
                return false;
            }
            $doZone = OA_Dal::factoryDO('zones');
            $doZone->get($zoneId);
            $zoneData = $doZone->toArray();

            $oZone = new OA_Dll_ZoneInfo();

            $this->_setZoneDataFromArray($oZone, $zoneData);
            return true;

        } else {

            $this->raiseError('Unknown zoneId Error');
            return false;
        }
    }

    /**
     * This method returns a list of zones for a publisher.
     *
     * @access public
     *
     * @param int $publisherId
     * @param array &$aZoneList
     *
     * @return boolean
     */
    function getZoneListByPublisherId($publisherId, &$aZoneList)
    {
        $aZoneList = array();

        if (!$this->checkIdExistence('affiliates', $publisherId)) {
                return false;
        }

        if (!$this->checkPermissions(null, 'affiliates', $publisherId)) {
            return false;
        }

        $doZone = OA_Dal::factoryDO('zones');
        $doZone->affiliateid = $publisherId;
        $doZone->find();

        while ($doZone->fetch()) {
            $zoneData = $doZone->toArray();

            $oZone = new OA_Dll_ZoneInfo();
            $this->_setZoneDataFromArray($oZone, $zoneData);

            $aZoneList[] = $oZone;
        }
        return true;
    }

    /**
     * This method returns daily statistics for a zone for a specified period.
     *
     * @access public
     *
     * @param integer $zoneId The ID of the zone to view statistics
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array $rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>day date</b> The day
     *   <li><b>requests integer</b> The number of requests for the day
     *   <li><b>impressions integer</b> The number of impressions for the day
     *   <li><b>clicks integer</b> The number of clicks for the day
     *   <li><b>revenue decimal</b> The revenue earned for the day
     *   </ul>
     *
     * @return boolean True if the operation was successful and false if not.
     *
     */
    function getZoneDailyStatistics($zoneId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($zoneId)) {
            return false;
        }

        if ($this->_validateForStatistics($zoneId, $oStartDate, $oEndDate)) {
            $dalZone = new OA_Dal_Statistics_Zone;
            $rsStatisticsData = $dalZone->getZoneDailyStatistics($zoneId,
                $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns daily statistics for a zone for a specified period.
     *
     * @access public
     *
     * @param integer $zoneId The ID of the zone to view statistics
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array $rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>advertiser ID integer</b> The ID of the advertiser
     *   <li><b>advertiserName string (255)</b> The name of the advertiser
     *   <li><b>requests integer</b> The number of requests for the advertiser
     *   <li><b>impressions integer</b> The number of impressions for the advertiser
     *   <li><b>clicks integer</b> The number of clicks for the advertiser
     *   <li>a<b>revenue decimal</b> The revenue earned for the advertiser
     *   </ul>
     *
     * @return boolean True if the operation was successful and false if not.
     *
     */

    function getZoneAdvertiserStatistics($zoneId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($zoneId)) {
            return false;
        }

        if ($this->_validateForStatistics($zoneId, $oStartDate, $oEndDate)) {
            $dalZone = new OA_Dal_Statistics_Zone;
            $rsStatisticsData = $dalZone->getZoneAdvertiserStatistics($zoneId,
                $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns campaign statistics for a zone for a specified period.
     *
     * @access public
     *
     * @param integer $zoneId The ID of the zone to view statistics
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array $rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>campaignID integer</b> The ID of the campaign
     *   <li><b>campaignName string</b> The name of the campaign
     *   <li><b>advertiserID integer</b> The ID advertiser
     *   <li><b>advertiserName string</b> The name advertiser
     *   <li><b>requests integer</b> The number of requests for the campaign
     *   <li><b>impressions integer</b> The number of impressions for the campaign
     *   <li><b>clicks integer</b> The number of clicks for the campaign
     *   <li><b>revenue decimal</b> The revenue earned for the campaign
     *   </ul>
     *
     * @return boolean True if the operation was successful and false if not.
     *
     */
    function getZoneCampaignStatistics($zoneId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($zoneId)) {
            return false;
        }

        if ($this->_validateForStatistics($zoneId, $oStartDate, $oEndDate)) {
            $dalZone = new OA_Dal_Statistics_Zone;
            $rsStatisticsData = $dalZone->getZoneCampaignStatistics($zoneId,
                $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns banner statistics for a zone for a specified period.
     *
     * @access public
     *
     * @param integer $zoneId The ID of the zone to view statistics
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array $rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>bannerID integer</b> The ID of the banner
     *   <li><b>bannerName string (255)</b> The name of the banner
     *   <li><b>campaignID integer</b> The ID of the banner
     *   <li><b>campaignName string (255)</b> The name of the banner
     *   <li><b>advertiserID integer</b> The ID of the advertiser
     *   <li><b>advertiserName string</b> The name of the advertiser
     *   <li><b>requests integer</b> The number of requests for the banner
     *   <li><b>impressions integer</b> The number of impressions for the banner
     *   <li><b>clicks integer</b> The number of clicks for the banner
     *   <li><b>revenue decimal</b> The revenue earned for the banner
     *   </ul>
     *
     * @return boolean True if the operation was successful and false if not.
     *
     */
    function getZoneBannerStatistics($zoneId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($zoneId)) {
            return false;
        }

        if ($this->_validateForStatistics($zoneId, $oStartDate, $oEndDate)) {
            $dalZone = new OA_Dal_Statistics_Zone;
            $rsStatisticsData = $dalZone->getZoneBannerStatistics($zoneId,
                $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Method checked if zone linked to active campaign
     *
     * @param int $zoneId
     * @return boolean  true if zone is connect to active campaign
     */
    function checkZoneLinkedToActiveCampaign($zoneId)
    {
        $doAdZone = OA_Dal::factoryDO('ad_zone_assoc');
        $doAdZone->zone_id = $zoneId;
        $doAdZone->find();
        $linkBanners = array();
        while ($doAdZone->fetch()) {
            if (!in_array($doAdZone->ad_id, $linkBanners)) {
                $linkBanners[] = $doAdZone->ad_id;
            }
        }

        foreach ($linkBanners as $bannerId) {
            $doBanner = OA_Dal::factoryDO('banners');
            $doBanner->get($bannerId);
            if (!in_array($doBanner->campaignid, $linkCampaigns)) {
                $linkCampaigns[] = $doBanner->campaignid;
            }
        }

        foreach ($linkCampaigns as $campaignId) {
            $doCampaign = OA_Dal::factoryDO('campaigns');
            $doCampaign->get($campaignId);
            if ($doCampaign->status != OA_ENTITY_STATUS_EXPIRED ||
                    $doCampaign->status != OA_ENTITY_STATUS_REJECTED) {

                return true;
            }
        }

        return false;
    }

}

?>
