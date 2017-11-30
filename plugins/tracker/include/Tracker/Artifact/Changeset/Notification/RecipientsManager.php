<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tuleap\Tracker\Artifact\Changeset\Notification;

use Tracker_Artifact_Changeset;
use Tracker_FormElementFactory;
use UserManager;
use PFUser;

class RecipientsManager
{
    /**
     * @var Tracker_FormElementFactory
     */
    private $form_element_factory;
    /**
     * @var UserManager
     */
    private $user_manager;

    public function __construct(Tracker_FormElementFactory $form_element_factory, UserManager $user_manager)
    {
        $this->form_element_factory = $form_element_factory;
        $this->user_manager         = $user_manager;
    }

    /**
     * Get the recipients for notification
     *
     * @param Tracker_Artifact_Changeset $changeset Changeset
     * @param bool $is_update It is an update, not a new artifact
     *
     * @return array of [$recipient => $checkPermissions] where $recipient is a usenrame or an email and $checkPermissions is bool.
     */
    public function getRecipients(Tracker_Artifact_Changeset $changeset, $is_update)
    {
        // 1 Get from the fields
        $recipients = array();
        $changeset->forceFetchAllValues();
        foreach ($changeset->getValues() as $field_id => $current_changeset_value) {
            if ($field = $this->form_element_factory->getFieldById($field_id)) {
                if ($field->isNotificationsSupported() && $field->hasNotifications() && ($r = $field->getRecipients($current_changeset_value))) {
                    $recipients = array_merge($recipients, $r);
                }
            }
        }
        // 2 Get from the commentators
        $recipients = array_merge($recipients, $changeset->getArtifact()->getCommentators());
        $recipients = array_values(array_unique($recipients));


        //now force check perms for all this people
        $tablo = array();
        foreach ($recipients as $r) {
            $tablo[$r] = true;
        }

        // 3 Get from the global notif
        foreach ($changeset->getTracker()->getRecipients() as $r) {
            if ($r['on_updates'] == 1 || !$is_update) {
                foreach ($r['recipients'] as $recipient) {
                    $tablo[$recipient] = $r['check_permissions'];
                }
            }
        }
        $this->removeRecipientsThatMayReceiveAnEmptyNotification($changeset, $tablo);
        $this->removeRecipientsThatHaveUnsubscribedArtifactNotification($changeset, $tablo);

        return $tablo;
    }

    private function removeRecipientsThatMayReceiveAnEmptyNotification(Tracker_Artifact_Changeset $changeset, array &$recipients)
    {
        if ($changeset->getComment() && ! $changeset->getComment()->hasEmptyBody()) {
            return;
        }

        foreach ($recipients as $recipient => $check_perms) {
            if (! $check_perms) {
                continue;
            }

            $user = $this->getUserFromRecipientName($recipient);
            if (! $user || ! $this->userCanReadAtLeastOneChangedField($changeset, $user)) {
                unset($recipients[$recipient]);
            }
        }
    }

    private function userCanReadAtLeastOneChangedField(Tracker_Artifact_Changeset $changeset, PFUser $user)
    {
        foreach ($changeset->getValues() as $field_id => $current_changeset_value) {
            $field = $this->form_element_factory->getFieldById($field_id);
            $field_is_readable = $field && $field->userCanRead($user);
            $field_has_changed = $current_changeset_value && $current_changeset_value->hasChanged();
            if ($field_is_readable && $field_has_changed) {
                return true;
            }
        }
        return false;
    }


    private function removeRecipientsThatHaveUnsubscribedArtifactNotification(Tracker_Artifact_Changeset $changeset, array &$recipients)
    {
        $unsubscribers = $changeset->getArtifact()->getUnsubscribersIds();

        foreach ($recipients as $recipient => $check_perms) {
            $user = $this->getUserFromRecipientName($recipient);

            if (! $user || in_array($user->getId(), $unsubscribers)) {
                unset($recipients[$recipient]);
            }
        }
    }

    /**
     * @param string $recipient_name
     * @return null|\PFUser
     */
    public function getUserFromRecipientName($recipient_name)
    {
        $user = null;
        if (strpos($recipient_name, '@') !== false) {
            //check for registered
            $user = $this->user_manager->getUserByEmail($recipient_name);

            //user does not exist (not registered/mailing list) then it is considered as an anonymous
            if (! $user) {
                // don't call $um->getUserAnonymous() as it will always return the same instance
                // we don't want to override previous emails
                // So create new anonymous instance by hand
                $user = $this->user_manager->getUserInstanceFromRow(
                    array(
                        'user_id' => 0,
                        'email'   => $recipient_name,
                    )
                );
            }
        } else {
            //is a login
            $user = $this->user_manager->getUserByUserName($recipient_name);
        }

        return $user;
    }
}