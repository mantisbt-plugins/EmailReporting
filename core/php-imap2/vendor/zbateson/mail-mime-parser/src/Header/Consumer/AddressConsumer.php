<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\Token;
use ZBateson\MailMimeParser\Header\Part\AddressGroupPart;
use ZBateson\MailMimeParser\Header\Part\AddressPart;

/**
 * Parses a single part of an address header.
 * 
 * Represents a single part of a list of addresses.  A part could be one email
 * address, or one 'group' containing multiple addresses.  The consumer ends on
 * finding either a comma token, representing a separation between addresses, or
 * a semi-colon token representing the end of a group.
 * 
 * A single email address may consist of just an email, or a name and an email
 * address.  Both of these are valid examples of a From header:
 *  - From: jonsnow@winterfell.com
 *  - From: Jon Snow <jonsnow@winterfell.com>
 * 
 * Groups must be named, for example:
 *  - To: Winterfell: jonsnow@winterfell.com, Arya Stark <arya@winterfell.com>;
 *
 * Addresses may contain quoted parts and comments, and names may be mime-header
 * encoded.
 * 
 * @author Zaahid Bateson
 */
class AddressConsumer extends AbstractConsumer
{
    /**
     * Returns the following as sub-consumers:
     *  - {@see AddressGroupConsumer}
     *  - {@see CommentConsumer}
     *  - {@see QuotedStringConsumer}
     * 
     * @return AbstractConsumer[] the sub-consumers
     */
    protected function getSubConsumers()
    {
        return [
            $this->consumerService->getAddressGroupConsumer(),
            $this->consumerService->getAddressEmailConsumer(),
            $this->consumerService->getCommentConsumer(),
            $this->consumerService->getQuotedStringConsumer(),
        ];
    }
    
    /**
     * Overridden to return patterns matching end tokens ("," and ";"), and
     * whitespace.
     * 
     * @return string[] the patterns
     */
    public function getTokenSeparators()
    {
        return [ ',', ';', '\s+' ];
    }
    
    /**
     * Returns true for commas and semi-colons.
     * 
     * Although the semi-colon is not strictly the end token of an
     * AddressConsumer, it could end a parent AddressGroupConsumer.
     * 
     * @param string $token
     * @return boolean false
     */
    protected function isEndToken($token)
    {
        return ($token === ',' || $token === ';');
    }
    
    /**
     * AddressConsumer is "greedy", so this always returns true.
     * 
     * @param string $token
     * @return boolean false
     */
    protected function isStartToken($token)
    {
        return true;
    }
    
    /**
     * Checks if the passed part represents the beginning or end of an address
     * part (less than/greater than characters) and either appends the value of
     * the part to the passed $strValue, or sets up $strName
     * 
     * @param IHeaderPart $part
     * @param string $strName
     * @param string $strValue
     */
    private function processSinglePart(IHeaderPart $part, &$strName, &$strValue)
    {
        $pValue = $part->getValue();
        if ($part instanceof Token) {
            if ($pValue === '<') {
                $strName = $strValue;
                $strValue = '';
                return;
            } elseif ($pValue === '>') {
                return;
            }
        }
        $strValue .= $pValue;
    }
    
    /**
     * Performs final processing on parsed parts.
     * 
     * AddressConsumer's implementation looks for tokens representing the
     * beginning of an address part, to create a Part\AddressPart out of a
     * name/address pair, or assign the name part to a parsed
     * Part\AddressGroupPart returned from its AddressGroupConsumer
     * sub-consumer.
     * 
     * The returned array consists of a single element - either a
     * Part\AddressPart or a Part\AddressGroupPart.
     * 
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]|array
     */
    protected function processParts(array $parts)
    {
        $strName = '';
        $strEmail = '';
        foreach ($parts as $part) {
            if ($part instanceof AddressGroupPart) {
                return [
                    $this->partFactory->newAddressGroupPart(
                        $part->getAddresses(),
                        $strEmail
                    )
                ];
            } elseif ($part instanceof AddressPart) {
                $strName = $strEmail;
                $strEmail = $part->getEmail();
                break;
            }
            $strEmail .= $part->getValue();
        }
        return [ $this->partFactory->newAddressPart($strName, $strEmail) ];
    }
}
