<?php

namespace Vulcan\RapidObjectCreateAdmin;

use LittleGiant\SingleObjectAdmin\SingleObjectAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

/**
 * Same functionality as SingleObjectAdmin but instead of editing a single record, you can create many records rapidly
 *
 * @package Vulcan\RapidObjectCreate
 *
 * @author Reece Alexander
 */
class RapidObjectCreateAdmin extends SingleObjectAdmin
{
    public function canView($member = null)
    {
        return Permission::check("CMS_ACCESS_SingleObjectAdmin_Create");
    }

    public function providePermissions()
    {
        return [
            "CMS_ACCESS_RapidObjectCreate_Create" => [
                'name'     => "Access to create new records in Single Object Administration",
                'category' => 'CMS Access',
                'help'     => 'Allow the creation of new records in Single Object Administration'
            ]
        ];
    }

    /**
     * @return DataObject
     */
    public function getCurrentObject()
    {
        $objectClass = $this->config()->get('tree_class');

        /** @var DataObject|Versioned $object */
        $object = $objectClass::create();

        return $object;
    }
    
    /**
     * @param array $data
     * @param Form  $form
     *
     * @return mixed
     */
    public function doSave($data, $form)
    {
        $objectClass = static::config()->get('tree_class');

        /** @var DataObject|Versioned $object */
        $object = $objectClass::create();

        $currentStage = Versioned::get_stage();
        Versioned::set_stage('Stage');

        $controller = Controller::curr();
        if (!$object->canEdit()) {
            return $controller->httpError(403);
        }

        try {
            $form->saveInto($object);
            $object->write();
        } catch (ValidationException $e) {
            $result = $e->getResult();
            $form->loadMessagesFrom($result);

            $responseNegotiator = new PjaxResponseNegotiator([
                'CurrentForm' => function () use (&$form) {
                    return $form->forTemplate();
                },
                'default'     => function () use (&$controller) {
                    return $controller->redirectBack();
                }
            ]);
            if ($controller->getRequest()->isAjax()) {
                $controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
            }
            return $responseNegotiator->respond($controller->getRequest());
        }

        Versioned::set_stage($currentStage);
        if ($objectClass::has_extension(Versioned::class)) {
            if ($object->isPublished()) {
                $this->publish($data, $form);
            }
        }

        $link = '"' . $object->singular_name() . '"';
        $message = _t('GridFieldDetailForm.Saved', 'Saved {name} {link}', [
            'name' => $object->singular_name(),
            'link' => $link
        ]);

        $form->sessionMessage($message, 'good');
        $action = $this->edit(Controller::curr()->getRequest());

        return $action;
    }
}
