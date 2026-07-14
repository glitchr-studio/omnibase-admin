<?php

namespace Base\Admin\Controller;

/**
 * Zero-config default dashboard: mounted automatically at the registry's
 * urlPrefix (see AdminRouteLoader::addDashboardRoute()) whenever the app
 * hasn't wired its own. configureMenuItems() already defaults to
 * MenuBuilder::buildDefault() (one section-less link per registered CRUD),
 * so this class needs no body of its own to be usable out of the box.
 *
 * Apps that want a customized dashboard (grouped sections, extra widgets,
 * ...) define their own App\Admin\Controller\DashboardController extending
 * this class - AdminBundle aliases the whole Base\Admin\Controller
 * namespace onto App\Admin\Controller, the same App-wins convention used
 * for entities and repositories, so the app's version is simply what
 * "Base\Admin\Controller\DashboardController" resolves to the moment it
 * exists - no route or service wiring required on top.
 */
class DashboardController extends AbstractDashboardController
{
}
