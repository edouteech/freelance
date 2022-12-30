<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Payum\Core\Model\Token;
use App\Repository\PaymentTokenRepository;

/**
 * @ORM\Table
 * @ORM\Entity
 */
class PaymentToken extends Token
{
}