<?php

declare(strict_types=1);

/**
 * This file is part of the arphiver package.
 *
 * (c) Jordi Domènech Bonilla
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jdomenechb\Arphiver;


class Metadata
{
    /**
     * @var string[][]
     */
    private $foreignKeys;

    /**
     * @var array
     */
    private $description;

    /** @var array */
    private $fieldsThatNeedTreatment;

    /**
     * MetadataPDO constructor.
     *
     * @param \string[][] $foreignKeys
     * @param array       $description
     */
    public function __construct(array $foreignKeys, array $description)
    {
        $this->foreignKeys = $foreignKeys;
        $this->description = $description;
        $this->fieldsThatNeedTreatment = array_filter($description, static function ($value) {
            return isset($value['needsTreatment']) && $value['needsTreatment'];
        });
    }

    /**
     * @return \string[][]
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return array
     */
    public function description(): array
    {
        return $this->description;
    }

    /**
     * @return array
     */
    public function fieldsThatNeedTreatment(): array
    {
        return $this->fieldsThatNeedTreatment;
    }


}