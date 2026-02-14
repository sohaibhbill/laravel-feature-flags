<?php

namespace MugiWara\FeatureFlags\Contracts;

/**
 * Implement this interface on your User model to enable segment-based
 * feature flag targeting.
 *
 * Example:
 *
 *   class User extends Authenticatable implements HasFeatureSegments
 *   {
 *       public function getFeatureSegments(): array
 *       {
 *           return array_filter([$this->role, $this->plan, 'all_users']);
 *       }
 *   }
 *
 * A flag with metadata ['segments' => ['beta_testers']] will only be
 * enabled for users whose getFeatureSegments() includes 'beta_testers'.
 */
interface HasFeatureSegments
{
    /**
     * Return the list of segments this user belongs to.
     * e.g. ['beta_testers', 'pro_plan', 'staff']
     *
     * @return string[]
     */
    public function getFeatureSegments(): array;
}
