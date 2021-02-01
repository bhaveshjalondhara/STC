<?php

namespace Magefox\GoogleShopping\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * XmlFeed Model
     *
     * @var \Magefox\GoogleShopping\Model\Xmlfeed
     */
    protected $xmlFeed;

    /**
     * General Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $helper;

    /**
     * Result Forward Factory
     *
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $resultForward;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magefox\GoogleShopping\Model\Xmlfeed $xmlFeed,
        \Magefox\GoogleShopping\Helper\Data $helper,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory
    ) {
        $this->xmlFeed = $xmlFeed;
        $this->helper = $helper;
        $this->resultForwardFactory = $resultForwardFactory;
        parent::__construct($context);
    }
    
    public function execute()
    {
        $resultForward = $this->resultForwardFactory->create();

        if (!empty($this->helper->getConfig('enabled'))) {
           //header('Content-disposition: attachment; filename=shatri.xml');
           //header ("Content-Type:text/xml"); 
            header ( "Content-type: application/vnd.ms-excel" );
            header ( "Content-Disposition: attachment; filename=foo_bar.xls" );

            echo $this->xmlFeed->getFeed();

        } else {
            $resultForward->forward('noroute');
        }
    }
}