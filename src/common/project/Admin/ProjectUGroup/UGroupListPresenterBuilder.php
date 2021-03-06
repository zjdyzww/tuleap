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
 */

namespace Tuleap\Project\Admin\ProjectUGroup;

use CSRFSynchronizerToken;
use Project;
use ProjectUGroup;
use Tuleap\User\UserGroup\NameTranslator;
use UGroupManager;

class UGroupListPresenterBuilder
{
    /**
     * @var UGroupManager
     */
    private $ugroup_manager;

    public function __construct(UGroupManager $ugroup_manager)
    {
        $this->ugroup_manager = $ugroup_manager;
    }

    public function build(Project $project, CSRFSynchronizerToken $csrf)
    {
        $static_ugroups = $this->ugroup_manager->getStaticUGroups($project);
        $templates      = $this->getUGroupsThatCanBeUsedAsTemplate($static_ugroups);

        $ugroups = array();
        $this->injectDynamicUGroup($project, ProjectUGroup::PROJECT_ADMIN, $ugroups);
        if ($project->usesWiki()) {
            $this->injectDynamicUGroup($project, ProjectUGroup::WIKI_ADMIN, $ugroups);
        }

        if ($project->usesForum()) {
            $this->injectDynamicUGroup($project, ProjectUGroup::FORUM_ADMIN, $ugroups);
        }

        if ($project->usesNews()) {
            $this->injectDynamicUGroup($project, ProjectUGroup::NEWS_WRITER, $ugroups);
            $this->injectDynamicUGroup($project, ProjectUGroup::NEWS_ADMIN, $ugroups);
        }

        $can_be_deleted = true;
        foreach ($static_ugroups as $ugroup) {
            $ugroups[] = new UGroupPresenter($project, $ugroup, $can_be_deleted);
        }

        return new UGroupListPresenter($project, $ugroups, $templates, $csrf);
    }

    /**
     * @param \ProjectUGroup[] $static_ugroups
     * @return array
     */
    private function getUGroupsThatCanBeUsedAsTemplate(array $static_ugroups)
    {
        $ugroups = array();

        $ugroups[] = array(
            'id'       => 'cx_empty',
            'name'     => _('Empty group'),
            'selected' => 'selected="selected"'
        );

        $ugroups[] = array(
            'id'       => 'cx_members',
            'name'     => NameTranslator::getUserGroupDisplayName(NameTranslator::PROJECT_MEMBERS),
            'selected' => ''
        );

        $ugroups[] = array(
            'id'       => 'cx_admins',
            'name'     => NameTranslator::getUserGroupDisplayName(NameTranslator::PROJECT_ADMINS),
            'selected' => ''
        );

        foreach ($static_ugroups as $ugroup) {
            $ugroups[] = array(
                'id'       => $ugroup->getId(),
                'name'     => NameTranslator::getUserGroupDisplayName($ugroup->getName()),
                'selected' => ''
            );
        }

        return $ugroups;
    }

    private function injectDynamicUGroup(Project $project, $ugroup_id, &$ugroups)
    {
        $ugroup         = $this->ugroup_manager->getUGroup($project, $ugroup_id);
        $can_be_deleted = false;
        $ugroups[]      = new UGroupPresenter($project, $ugroup, $can_be_deleted);
    }
}
