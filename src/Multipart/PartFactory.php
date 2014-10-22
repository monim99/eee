<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Http
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Http\Multipart;

use Aura\Http\Header\HeaderCollection;
use Aura\Http\Header\HeaderFactory;

/**
 *
 * A factory to create message parts.
 *
 * @package Aura.Http
 *
 */
class PartFactory
{
    /**
     *
     * Returns a new part.
     *
     * @return Part
     *
     */
    public function newInstance()
    {
        return new Part(new HeaderCollection(new HeaderFactory));
    }
}
