<?php

namespace Arkounay\BlockI18nBundle\Controller;

use Arkounay\BlockI18nBundle\Entity\PageBlock;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class AjaxController
 */
class AjaxController extends Controller
{

    /**
     * Allows saving of multiple blocks at once
     * @Route("/arkounay-ajax-edit-pageblocks", name="arkounay_pageblocks_ajax")
     * @param Request $request
     * @return Response
     */
    public function ajaxEditAction(Request $request)
    {
        if (!$this->get('arkounay_block_service')->hasInlineEditPermissions()) {
            throw $this->createAccessDeniedException();
        }

        $blocks = $request->request->get('blocks');
        if (!empty($blocks)) {
            $em = $this->getDoctrine()->getManager();
            foreach ($blocks as $block) {
                if (isset($block['id'], $block['content'])) {
                    if (isset($block['entity'], $block['field'])) {
                        // Generic entity
                        $entity = $em->getRepository($block['entity'])->find($block['id']);
                        if ($entity === null) {
                            throw new \UnexpectedValueException("The entity {$block['entity']} (id:{$block['id']}) could not be found.");
                        }
                        $content = $block['content'];
                        if (method_exists($entity, 'translate') && !method_exists($entity, 'set' . ucFirst($block['field']))) {
                            call_user_func_array([$entity->translate(null, false), 'set' . ucfirst($block['field'])], [$content]);
                            if (method_exists($entity, 'mergeNewTranslations')) {
                                $entity->mergeNewTranslations();
                            }
                        } else {
                            $accessor = PropertyAccess::createPropertyAccessor();
                            $accessor->setValue($entity, $block['field'], $block['content']);
                        }

                        $em->persist($entity);
                    } else {
                        // PageBlock entity
                        $pageBlock = $em->getRepository('ArkounayBlockI18nBundle:PageBlock')->find($block['id']);
                        if ($pageBlock === null) {
                            $pageBlock = new PageBlock();
                            $pageBlock->setId($block['id']);
                        }
                        $pageBlock->translate(null, false)->setContent($block['content']);
                        if (method_exists($pageBlock, 'mergeNewTranslations')) {
                            $pageBlock->mergeNewTranslations();
                        }
                        $em->persist($pageBlock);
                    }
                }
            }
            $em->flush();
        }


        return new Response();
    }

}
