<?php
namespace STC\StcPaymentGateway\Model\ResourceModel\MobilePayment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
	protected $_idFieldName = 'id';
	protected $_eventPrefix = 'stc_stcpaymentgateway_mobilepayment_collection';
	protected $_eventObject = 'stcpaymentgateway_collection';

	/**
	 * Define resource model
	 *
	 * @return void
	 */
	protected function _construct()
	{
		$this->_init('STC\StcPaymentGateway\Model\MobilePayment', 'STC\StcPaymentGateway\Model\ResourceModel\MobilePayment');
	}

}