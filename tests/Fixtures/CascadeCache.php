<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace craft\cachecascade;

use yii\caching\ArrayCache;

if (!class_exists(CascadeCache::class)) {
    /**
     * Opaque managed-cache test fixture.
     *
     * @since 5.28.4
     */
    final class CascadeCache extends ArrayCache
    {
        /** @var list<int> */
        public array $setDurations = [];

        public function set($key, $value, $duration = null, $dependency = null)
        {
            $this->setDurations[] = (int)$duration;

            return parent::set($key, $value, $duration, $dependency);
        }

        public function hiddenPrimary(): never
        {
            throw new \LogicException('The hidden primary must remain opaque.');
        }
    }
}
