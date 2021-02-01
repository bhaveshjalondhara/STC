<?php
namespace STC\StcPaymentGateway\Model;
class MobilePayment extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
	const CACHE_TAG = 'stc_stcpaymentgateway_mobilepayment';

	protected $_cacheTag = 'stc_stcpaymentgateway_mobilepayment';

	protected $_eventPrefix = 'stc_stcpaymentgateway_mobilepayment';

	protected function _construct()
	{
		$this->_init('STC\StcPaymentGateway\Model\ResourceModel\MobilePayment');
	}

	public function getIdentities()
	{
		return [self::CACHE_TAG . '_' . $this->getId()];
	}

	public function getDefaultValues()
	{
		$values = [];

		return $values;
	}
}