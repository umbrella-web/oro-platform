<?php

namespace Oro\Bundle\DashboardBundle\Controller;

use Doctrine\ORM\EntityManager;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\DashboardBundle\Entity\Repository\DashboardRepository;
use Oro\Bundle\DashboardBundle\Entity\Dashboard;
use Oro\Bundle\DashboardBundle\Model\DashboardModel;
use Oro\Bundle\DashboardBundle\Model\Manager;
use Oro\Bundle\DashboardBundle\Model\WidgetAttributes;
use Oro\Bundle\SecurityBundle\SecurityFacade;

/**
 * @Route("/dashboard")
 */
class DashboardController extends Controller
{
    /**
     * @Route(
     *      ".{_format}",
     *      name="oro_dashboard_index",
     *      requirements={"_format"="html|json"},
     *      defaults={"_format" = "html"}
     * )
     *
     * @Acl(
     *      id="oro_dashboard_view",
     *      type="entity",
     *      class="OroDashboardBundle:Dashboard",
     *      permission="VIEW"
     * )
     * @Template
     */
    public function indexAction()
    {
        return [
            'entity_class' => $this->container->getParameter('oro_dashboard.dashboard_entity.class')
        ];
    }

    /**
     * @param Dashboard $dashboard
     *
     * @Route(
     *      "/view/{id}",
     *      name="oro_dashboard_view",
     *      requirements={"id"="\d+"},
     *      defaults={"id" = "0"}
     * )
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(Dashboard $dashboard = null)
    {
        $currentDashboard = $this->findAllowedDashboard($dashboard);

        if (!$currentDashboard) {
            return $this->quickLaunchpadAction();
        }

        if (!$this->getSecurityFacade()->isGranted('VIEW', $currentDashboard->getEntity())) {
            return $this->quickLaunchpadAction();
        }

        $changeActive = $this->get('request')->get('change_dashboard', false);
        if ($changeActive && $dashboard) {
            $this->getDashboardManager()->setUserActiveDashboard(
                $currentDashboard,
                $this->getUser(),
                true
            );
        }

        return $this->render(
            $currentDashboard->getTemplate(),
            array(
                'dashboards' => $this->getDashboardManager()->findAllowedDashboards(),
                'dashboard'  => $currentDashboard,
                'widgets'    => $this->get('oro_dashboard.config_provider')->getWidgetConfigs()
            )
        );
    }

    /**
     * @Route("/update/{id}", name="oro_dashboard_update", requirements={"id"="\d+"},  defaults={"id"=0})
     * @Acl(
     *      id="oro_dashboard_update",
     *      type="entity",
     *      class="OroDashboardBundle:Dashboard",
     *      permission="EDIT"
     * )
     *
     * @Template()
     */
    public function updateAction(Dashboard $dashboard)
    {
        $dashboardModel = $this->getDashboardManager()->getDashboardModel($dashboard);
        return $this->update($dashboardModel);
    }

    /**
     * @Route("/create", name="oro_dashboard_create")
     * @Acl(
     *      id="oro_dashboard_create",
     *      type="entity",
     *      class="OroDashboardBundle:Dashboard",
     *      permission="CREATE"
     * )
     * @Template("OroDashboardBundle:Dashboard:update.html.twig")
     */
    public function createAction()
    {
        $dashboardModel = $this->getDashboardManager()->createDashboardModel();
        return $this->update($dashboardModel);
    }

    /**
     * @param DashboardModel $dashboardModel
     * @return mixed
     */
    protected function update(DashboardModel $dashboardModel)
    {
        $form = $this->createForm(
            $this->container->get('oro_dashboard.form.type.edit'),
            $dashboardModel->getEntity(),
            array(
                'create_new' => !$dashboardModel->getId()
            )
        );

        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            if ($form->submit($request)->isValid()) {
                $this->getDashboardManager()->save($dashboardModel, true);
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $this->get('translator')->trans('oro.dashboard.saved_message')
                );

                return $this->get('oro_ui.router')->redirectAfterSave(
                    array(
                        'route'      => 'oro_dashboard_update',
                        'parameters' => array(
                            'id' => $dashboardModel->getId(),
                            '_enableContentProviders' => 'mainMenu'
                        ),
                    ),
                    array(
                        'route'      => 'oro_dashboard_view',
                        'parameters' => array(
                            'id' => $dashboardModel->getId(),
                            'change_dashboard' => true,
                            '_enableContentProviders' => 'mainMenu'
                        ),
                    )
                );
            }
        }

        return array('entity' => $dashboardModel, 'form' => $form->createView());
    }

    /**
     * @Route(
     *      "/widget/{widget}/{bundle}/{name}",
     *      name="oro_dashboard_widget",
     *      requirements={"widget"="[\w-]+", "bundle"="\w+", "name"="[\w-]+"}
     * )
     */
    public function widgetAction($widget, $bundle, $name)
    {
        return $this->render(
            sprintf('%s:Dashboard:%s.html.twig', $bundle, $name),
            $this->get('oro_dashboard.widget_attributes')->getWidgetAttributesForTwig($widget)
        );
    }

    /**
     * @Route(
     *      "/itemized_widget/{widget}/{bundle}/{name}",
     *      name="oro_dashboard_itemized_widget",
     *      requirements={"widget"="[\w-]+", "bundle"="\w+", "name"="[\w-]+"}
     * )
     */
    public function itemizedWidgetAction($widget, $bundle, $name)
    {
        /** @var WidgetAttributes $manager */
        $manager = $this->get('oro_dashboard.widget_attributes');

        $params = array_merge(
            [
                'items' => $manager->getWidgetItems($widget)
            ],
            $manager->getWidgetAttributesForTwig($widget)
        );

        return $this->render(
            sprintf('%s:Dashboard:%s.html.twig', $bundle, $name),
            $params
        );
    }

    /**
     * @Route(
     *      "/launchpad",
     *      name="oro_dashboard_quick_launchpad"
     * )
     */
    public function quickLaunchpadAction()
    {
        return $this->render(
            'OroDashboardBundle:Index:quickLaunchpad.html.twig',
            [
                'dashboards' => $this->getDashboardManager()->findAllowedDashboards(),
            ]
        );
    }

    /**
     * Get dashboard with granted permission. If dashboard id is not specified, gets current active or default dashboard
     *
     * @param Dashboard $dashboard $dashboard
     * @param string    $permission
     * @return DashboardModel|null
     */
    protected function findAllowedDashboard(Dashboard $dashboard = null, $permission = 'VIEW')
    {
        if ($dashboard) {
            $dashboard = $this->getDashboardManager()->getDashboardModel($dashboard);
        } else {
            $dashboard = $this->getDashboardManager()->findUserActiveOrDefaultDashboard($this->getUser());
            if ($dashboard &&
                !$this->getSecurityFacade()->isGranted($permission, $dashboard->getEntity())
            ) {
                $dashboard = null;
            }
        }

        return $dashboard;
    }

    /**
     * @return Manager
     */
    protected function getDashboardManager()
    {
        return $this->get('oro_dashboard.manager');
    }

    /**
     * @return DashboardRepository
     */
    protected function getDashboardRepository()
    {
        return $this->getDoctrine()->getRepository('OroDashboardBundle:Dashboard');
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }

    /**
     * @return SecurityFacade
     */
    protected function getSecurityFacade()
    {
        return $this->get('oro_security.security_facade');
    }
}
