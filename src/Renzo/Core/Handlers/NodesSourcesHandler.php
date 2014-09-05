<?php
/*
 * Copyright REZO ZERO 2014
 *
 *
 * @file NodesSourcesHandler.php
 * @copyright REZO ZERO 2014
 * @author Ambroise Maupate
 */
namespace RZ\Renzo\Core\Handlers;

use RZ\Renzo\Core\Entities\NodesSources;
use RZ\Renzo\Core\Entities\Document;
use RZ\Renzo\Core\Entities\NodesSourcesDocuments;
use RZ\Renzo\Core\Entities\NodeTypeField;
use RZ\Renzo\Core\Kernel;
use Symfony\Component\Finder\Finder;
/**
 * Handle operations with node-sources entities.
 */
class NodesSourcesHandler
{
    protected $nodeSource;

    /**
     * Create a new node-source handler with node-source to handle.
     *
     * @param RZ\Renzo\Core\Entities\NodesSources $nodeSource
     */
    public function __construct($nodeSource)
    {
        $this->nodeSource = $nodeSource;
    }


    /**
     * Remove every node-source documents associations for a given field.
     *
     * @param \RZ\Renzo\Core\Entities\NodeTypeField $field
     *
     * @return $this
     */
    public function cleanDocumentsFromField(NodeTypeField $field)
    {
        $nsDocuments = Kernel::getInstance()->em()
                ->getRepository('RZ\Renzo\Core\Entities\NodesSourcesDocuments')
                ->findBy(array('nodeSource'=>$this->nodeSource, 'field'=>$field));

        foreach ($nsDocuments as $nsDoc) {
            Kernel::getInstance()->em()->remove($nsDoc);
            Kernel::getInstance()->em()->flush();
        }

        return $this;
    }

    /**
     * Add a document to current node-source for a given node-type field.
     *
     * @param Document      $document
     * @param NodeTypeField $field
     *
     * @return $this
     */
    public function addDocumentForField(Document $document, NodeTypeField $field)
    {
        $nsDoc = new NodesSourcesDocuments($this->nodeSource, $document, $field);

        $latestPosition = Kernel::getInstance()->em()
                ->getRepository('RZ\Renzo\Core\Entities\NodesSourcesDocuments')
                ->getLatestPosition($this->nodeSource, $field);

        $nsDoc->setPosition($latestPosition + 1);

        Kernel::getInstance()->em()->persist($nsDoc);
        Kernel::getInstance()->em()->flush();

        return $this;
    }

    /**
     * Get documents linked to current node-source for a given fieldname.
     *
     * @param string $fieldName Name of the node-type field
     *
     * @return ArrayCollection Collection of documents
     */
    public function getDocumentsFromFieldName($fieldName)
    {
        return Kernel::getInstance()->em()
                ->getRepository('RZ\Renzo\Core\Entities\Document')
                ->findByNodeSourceAndFieldName($this->nodeSource, $fieldName);
    }

    /**
     * @return string Current node-source URL
     */
    public function getUrl()
    {
        if ($this->nodeSource->getNode()->isHome()) {
            return Kernel::getInstance()->getRequest()->getBaseUrl();
        }

        $urlTokens = array();
        $urlTokens[] = $this->getIdentifier();

        $parent = $this->getParent();
        if ($parent !== null &&
            !$parent->getNode()->isHome()) {

            do {
                $handler = $parent->getHandler();
                $urlTokens[] = $handler->getIdentifier();
                $parent = $parent->getHandler()->getParent();
            } while ($parent !== null && !$parent->getNode()->isHome());
        }

        /*
         * If using node-name, we must use shortLocale
         */
        if ($urlTokens[0] == $this->nodeSource->getNode()->getNodeName()) {
            $urlTokens[] = $this->nodeSource->getTranslation()->getShortLocale();
        }

        $urlTokens[] = Kernel::getInstance()->getRequest()->getBaseUrl();
        $urlTokens = array_reverse($urlTokens);

        return implode('/', $urlTokens);
    }

    /**
     * Get a string describing uniquely the curent nodeSource.
     *
     * Can be the urlAlias or the nodeName
     *
     * @return string
     */
    public function getIdentifier()
    {
        $urlalias = $this->nodeSource->getUrlAliases()->first();
        if ($urlalias != null) {
            return $urlalias->getAlias();
        } else {
            return $this->nodeSource->getNode()->getNodeName();
        }
    }

    /**
     * Get parent node-source to get the current translation.
     *
     * @return NodesSources
     */
    public function getParent()
    {
        $parent = $this->nodeSource->getNode()->getParent();
        if ($parent !== null) {
            $query = Kernel::getInstance()->em()
                            ->createQuery('
                SELECT ns FROM RZ\Renzo\Core\Entities\NodesSources ns
                WHERE ns.node = :node
                AND ns.translation = :translation'
                            )->setParameter('node', $parent)
                            ->setParameter('translation', $this->nodeSource->getTranslation());

            try {
                return $query->getSingleResult();
            } catch (\Doctrine\ORM\NoResultException $e) {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Get children nodes sources to lock with current translation.
     *
     * @return ArrayCollection NodesSources collection
     */
    public function getChildren()
    {
         $query = Kernel::getInstance()->em()
                        ->createQuery('
            SELECT ns FROM RZ\Renzo\Core\Entities\NodesSources ns
            INNER JOIN ns.node n
            WHERE n.parent = :parent
            AND ns.translation = :translation
            ORDER BY n.position ASC')
                        ->setParameter('parent', $this->nodeSource->getNode())
                        ->setParameter('translation', $this->nodeSource->getTranslation());

        try {
            return $query->getResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }
}