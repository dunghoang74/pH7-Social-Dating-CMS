<?php
/**
 * @author         Pierre-Henry Soria <ph7software@gmail.com>
 * @copyright      (c) 2012-2018, Pierre-Henry Soria. All Rights Reserved.
 * @license        GNU General Public License; See PH7.LICENSE.txt and PH7.COPYRIGHT.txt in the root directory.
 * @package        PH7 / App / System / Module / User / Form / Processing
 */

namespace PH7;

defined('PH7') or exit('Restricted access');

use PH7\Framework\Cache\Cache;
use PH7\Framework\Mvc\Request\Http;
use stdClass;

class EditFormProcess extends Form
{
    /**
     * @param int $iProfileId
     *
     * @throws Framework\Mvc\Request\WrongRequestMethodException
     */
    public function __construct($iProfileId)
    {
        parent::__construct();

        $oUserModel = new UserModel;
        $oUser = $oUserModel->readProfile($iProfileId);

        if ($this->isOnlyAdminLoggedAndUserIdExists()) {
            $this->updateUserMembership($iProfileId, $oUser, $oUserModel);
        }

        if (!$this->str->equals($this->httpRequest->post('first_name'), $oUser->firstName)) {
            $oUserModel->updateProfile('firstName', $this->httpRequest->post('first_name'), $iProfileId);
            $this->session->set('member_first_name', $this->httpRequest->post('first_name'));

            $this->clearFieldCache('firstName', $iProfileId);
        }

        if (!$this->str->equals($this->httpRequest->post('last_name'), $oUser->lastName)) {
            $oUserModel->updateProfile('lastName', $this->httpRequest->post('last_name'), $iProfileId);
        }

        if (!$this->str->equals($this->httpRequest->post('sex'), $oUser->sex)) {
            $oUserModel->updateProfile('sex', $this->httpRequest->post('sex'), $iProfileId);
            $this->session->set('member_sex', $this->httpRequest->post('sex'));

            $this->clearFieldCache('sex', $iProfileId);
        }

        // WARNING: Be careful, you should use the Http::NO_CLEAN constant, otherwise Http::post() method removes the special tags
        // and damages the SET function SQL for entry into the database.
        if (!$this->str->equals($this->httpRequest->post('match_sex', Http::NO_CLEAN), $oUser->matchSex)) {
            $oUserModel->updateProfile('matchSex', Form::setVal($this->httpRequest->post('match_sex', Http::NO_CLEAN)), $iProfileId);
        }

        if (!$this->str->equals($this->dateTime->get($this->httpRequest->post('birth_date'))->date('Y-m-d'), $oUser->birthDate)) {
            $oUserModel->updateProfile('birthDate', $this->dateTime->get($this->httpRequest->post('birth_date'))->date('Y-m-d'), $iProfileId);
        }

        // Update dynamic fields
        $oFields = $oUserModel->getInfoFields($iProfileId);
        foreach ($oFields as $sColumn => $sValue) {
            $sHRParam = ($sColumn === 'description') ? Http::ONLY_XSS_CLEAN : null;
            if (!$this->str->equals($this->httpRequest->post($sColumn, $sHRParam), $sValue)) {
                $oUserModel->updateProfile($sColumn, $this->httpRequest->post($sColumn, $sHRParam), $iProfileId, DbTableName::MEMBER_INFO);
            }
        }
        unset($oFields);

        $oUserModel->setLastEdit($iProfileId);

        /*** Clear caches ***/
        $oUserCache = new User;
        $oUserCache->clearReadProfileCache($iProfileId);
        $oUserCache->clearInfoFieldCache($iProfileId);

        // Destroy objects
        unset($oUserModel, $oUser, $oUserCache);

        \PFBC\Form::setSuccess(
            'form_user_edit_account',
            t('The profile has been successfully updated')
        );
    }

    /**
     * Allow admins to update user's membership.
     *
     * @param int $iProfileId
     * @param stdClass $oUser
     * @param UserModel $oUserModel
     *
     * @throws Framework\Mvc\Request\WrongRequestMethodException
     */
    private function updateUserMembership($iProfileId, stdClass $oUser, UserModel $oUserModel)
    {
        if (!$this->str->equals($this->httpRequest->post('group_id'), $oUser->groupId)) {
            $oUserModel->updateMembership(
                $this->httpRequest->post('group_id'),
                $iProfileId,
                $this->dateTime->get()->dateTime(UserCoreModel::DATETIME_FORMAT)
            );

            $this->clearFieldCache('membershipDetails', $iProfileId);
        }
    }

    /**
     * @return bool
     */
    private function isOnlyAdminLoggedAndUserIdExists()
    {
        return AdminCore::auth() && !User::auth() && $this->httpRequest->getExists('profile_id');
    }

    /**
     * @param string $sCacheId
     * @param int $iProfileId
     *
     * @return void
     */
    private function clearFieldCache($sCacheId, $iProfileId)
    {
        (new Cache)->start(
            UserCoreModel::CACHE_GROUP,
            $sCacheId . $iProfileId . DbTableName::MEMBER,
            null
        )->clear();
    }
}
