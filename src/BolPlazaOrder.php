<?php 
namespace MCS;
 
use DateTime;
use Exception;
use DOMDocument;
use DateTimeZone;
use MCS\BolPlazaOrderItem;
use MCS\BolPlazaOrderAddress;

class BolPlazaOrder{

    private $client;
    public $id;
    public $ShippingAddress;
    public $BillingAddress;
    public $OrderItems = [];
    
    /**
     * Construct
     * @param string $id The orderId
     * @param array $ShippingAddress 
     * @param array $BillingAddress  
     * @param object BolPlazaClient $client 
     */
    public function __construct($id, array $ShippingAddress, array $BillingAddress, BolPlazaClient $client)
    {
        $this->id = $id;
        $this->ShippingAddress = new BolPlazaOrderAddress($ShippingAddress);
        $this->BillingAddress = new BolPlazaOrderAddress($BillingAddress);
        $this->client = $client;
    }
    
    /**
     * Add an item to the order
     * @param array $item
     */
    public function addOrderItem(array $item)
    {
        $this->OrderItems[] = new BolPlazaOrderItem($item, $this->client);
    }
    
    /**
     * Ship an order
     * @param  object DateTime $expectedDeliveryDate 
     * @param  string [$carrier = false]             
     * @param  parcelnumber [$awb = false]                 
     * @return array
     */
    public function ship(DateTime $expectedDeliveryDate, $carrier = false, $awb = false)
    {
     
        $carriers = [
            'BPOST_BRIEF', 'BRIEFPOST', 'GLS', 'FEDEX_NL',
            'DHLFORYOU', 'UPS', 'KIALA_BE', 'KIALA_NL',
            'DYL', 'DPD_NL', 'DPD_BE', 'BPOST_BE',
            'FEDEX_BE', 'OTHER', 'DHL', 'SLV',
            'TNT', 'TNT_EXTRA', 'TNT_BRIEF'
        ];  
        
        if ($carrier && !in_array($carrier, $carriers)) {
            throw new Exception('Carrier not allowed. Use one of: ' . implode(' / ', $carriers));    
        }
        
        $timeZone = new DateTimeZone('Etc/Greenwich');
        $format = 'Y-m-d\TH:i:s';
        
        $now = new DateTime();
        $now = $now->setTimezone($timeZone);
        
        $expected = $expectedDeliveryDate->setTimezone($timeZone);
        
        $response = [];
        
        foreach ($this->OrderItems as $OrderItem) {
            $xml = new DOMDocument('1.0', 'UTF-8');

            $body = $xml->appendChild(
                $xml->createElementNS('https://plazaapi.bol.com/services/xsd/v2/plazaapi.xsd', 'ShipmentRequest')
            );
            $body->appendChild(
                $xml->createElement('OrderItemId', $OrderItem->OrderItemId)
            );
            $body->appendChild(
                $xml->createElement('ShipmentReference', $OrderItem->Title)
            );
            $body->appendChild(
                $xml->createElement('DateTime', $now->format($format))
            );
            $body->appendChild(
                $xml->createElement('ExpectedDeliveryDate', $expected->format(DATE_ISO8601))
            );
            
            if ($carrier && $awb) {
                $transport = $body->appendChild(
                    $xml->createElement('Transport')
                );
                $transport->appendChild(
                    $xml->createElement('TransporterCode', $carrier)
                );
                $transport->appendChild(
                    $xml->createElement('TrackAndTrace', $awb)
                ); 
            }
            
            $response[] = $this->client->request($this->client->endPoints['shipments'], 'POST', $xml->saveXML());
        }
        
        return $response;
        
    }
}