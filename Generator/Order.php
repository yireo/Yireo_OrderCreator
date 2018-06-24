<?php

namespace Yireo\OrderCreator\Generator;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Service\OrderService;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Yireo\OrderCreator\Exception\InvalidAmount;
use Yireo\OrderCreator\Exception\NoCustomerObject;

class Order
{
    /**
     * @var ProductInterface[]
     */
    private $products = [];

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductModel
     */
    private $product;

    /**
     * @var FormKey
     */
    private $formkey;

    /**
     * @var QuoteFactory
     */
    private $quote;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepositoryInterface;
    /**
     * @var Rate
     */
    private $shippingRate;

    /**
     * Order constructor.
     * @param StoreManagerInterface $storeManager
     * @param QuoteManagement $quoteManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param ProductRepositoryInterface $productRepository
     * @param Rate $shippingRate
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        QuoteManagement $quoteManagement,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $cartRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        Rate $shippingRate
    )
    {
        $this->storeManager = $storeManager;
        $this->quoteManagement = $quoteManagement;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->shippingRate = $shippingRate;
    }

    /**
     * @param $email
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setCustomerEmail($email)
    {
        $customer = $this->customerRepository->get($email);

        if ($customer) {
            $this->customer = $customer;
        }
    }

    /**
     * @param string $sku
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function addProductBySku(string $sku)
    {
        $product = $this->productRepository->get($sku);
        $this->addProduct($product);
    }

    /**
     * @param ProductInterface $product
     */
    public function addProduct(ProductInterface $product)
    {
        $this->products[] = $product;
    }

    /**
     * @return OrderInterface
     * @throws NoCustomerObject
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generate()
    {
        if (!$this->customer) {
            throw new NoCustomerObject(__('No customer object loaded'));
        }

        $store = $this->getStore();
        $websiteId = $this->getWebsiteId();
        $this->customer->setWebsiteId($websiteId);

        /** @var Quote $quote */
        $quoteId = $this->quoteManagement->createEmptyCartForCustomer($this->customer->getId());
        $quote = $this->cartRepositoryInterface->get($quoteId);
        $quote->setStore($store);
        $quote->setCurrency();

        $subtotal = 0;
        foreach ($this->products as $product) {
            $price = 0;
            $qty = 1;
            $product->setPrice($price);
            $product->setCustomPrice($price);
            $product->setOriginalCustomPrice($price);
            $quote->addProduct(
                $product,
                $qty
            );
            $subtotal = $subtotal + ($price * $qty);
        }

        $customerAddress = $this->getCustomerAddress();
        $quote->getBillingAddress()->importCustomerAddressData($customerAddress);
        $quote->getShippingAddress()->importCustomerAddressData($customerAddress);

        $this->shippingRate
            ->setCode('freeshipping_freeshipping')
            ->getPrice();

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate');

        $quote->setShippingAddress($shippingAddress);

        $quote->setPaymentMethod('free');
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $quote->setInventoryProcessed(false);

        $quote->collectTotals();
        if ($quote->getBaseSubtotal() > $subtotal) {
            throw new InvalidAmount(sprintf('Subtotal is %s while expecting %s', $quote->getSubtotal(), $subtotal));
        }

        $quote->save();
        $order = $this->quoteManagement->submit($quote);

        $order->setEmailSent(0);
        return $order;
    }

    /**
     * @return AddressInterface
     */
    private function getCustomerAddress(): AddressInterface
    {
        $customerAddresses = $this->customer->getAddresses();
        $customerAddress = array_shift($customerAddresses);
        return $customerAddress;
    }

    /**
     * @return int
     */
    private function getWebsiteId(): int
    {
        return $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @return Store
     */
    private function getStore(): Store
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        return $store;
    }

    public function getOrderData()
    {
        return [
            'currency_id' => 'USD',
            'email' => 'helloworld@mageplaza.com', //buyer email id
            'shipping_address' => [
                'firstname' => 'John', //address Details
                'lastname' => 'Doe',
                'street' => '123 Demo',
                'city' => 'Mageplaza',
                'country_id' => 'US',
                'region' => 'xxx',
                'postcode' => '10019',
                'telephone' => '0123456789',
                'fax' => '32423',
                'save_in_address_book' => 1
            ],
            'items' => [
                ['product_id' => '1', 'qty' => 1],
                ['product_id' => '2', 'qty' => 2]
            ]
        ];
    }
}