<?php namespace meltingmedia\package;

/**
 * Service class to help validate requirements
 */
class Validator extends Service
{
    /**
     * Check for system requirements (ie. MODX version, PHP version)
     *
     * @return bool
     */
    public function checkSystem()
    {
        $name = $this->getDependency('package_name');
        $required = $this->getDependency('package_version');

        $success = true;
        $toCheck = false;
        switch ($name) {
            case 'modx':
                $v = $this->modx->getVersionData();
                $toCheck = $v['full_version'];
                break;
            case 'php':
                $toCheck = XPDO_PHP_VERSION;
                break;
        }

        if ($toCheck) {
            $success = $this->satisfies($toCheck, $required);
        }

        return $success;
    }

    /**
     * Check if we have a matching version already in place
     *
     * @return bool
     */
    public function isLocalMatch()
    {
        $name = $this->getDependency('package_name');
        $required = $this->getDependency('package_version');

        $c = $this->modx->newQuery('modTransportPackage');
        $c->where(array(
            'package_name' => strtolower($name),
            'OR:package_name:=' => $name,
        ));
        $c->sortby('installed', 'DESC');
        //$c->limit(1);

        $collection = $this->modx->getCollection('modTransportPackage', $c);

        /** @var \modTransportPackage $candidate */
        foreach ($collection as $candidate) {
            $version = $candidate->getComparableVersion();
            if ($this->satisfies($version, $required)) {
                // We got a match
                // @todo check if installed, if not, install it
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether or not a local package is present
     *
     * @return bool
     */
    public function haveLocalPackage()
    {
        $name = $this->getDependency('package_name');

        $c = $this->modx->newQuery('modTransportPackage');
        $c->where(array(
            'package_name' => strtolower($name),
            'OR:package_name:=' => $name,
        ));
        $c->limit(1);

        /** @var \modTransportPackage $object */
        $object = $this->modx->getObject('modTransportPackage', $c);

        return $object instanceof \modTransportPackage;
    }


    /**
     * Test if a version satisfies a version constraint.
     *
     * @param string $version The version to test.
     * @param string $constraint The constraint to satisfy.
     *
     * @author Jason Coward
     *
     * @return bool TRUE if the version satisfies the constraint; FALSE otherwise.
     */
    public static function satisfies($version, $constraint)
    {
        $satisfied = false;
        $constraint = trim($constraint);
        if (substr($constraint, 0, 1) === '~') {
            $requirement = substr($constraint, 1);
            $constraint = ">={$requirement},<" . self::nextSignificantRelease($requirement);
        }
        if (strpos($constraint, ',') !== false) {
            $exploded = explode(',', $constraint);
            array_walk($exploded, 'trim');
            $satisfies = array();
            foreach ($exploded as $requirement) {
                $satisfies[] = self::satisfies($version, $requirement);
            }
            $satisfied = (false === array_search(false, $satisfies, true));
        } elseif (($wildcardPos = strpos($constraint, '.*')) > 0) {
            $requirement = substr($constraint, 0, $wildcardPos + 1);
            $requirements = array(
                ">=" . $requirement,
                "<" . self::nextSignificantRelease($requirement)
            );
            $satisfies = array();
            foreach ($requirements as $requires) {
                $satisfies[] = self::satisfies($version, $requires);
            }
            $satisfied = (false === array_search(false, $satisfies, true));
        } elseif (in_array(substr($constraint, 0, 1), array('<', '>', '!'))) {
            $operator = substr($constraint, 0, 1);
            $versionPos = 1;
            if (substr($constraint, 1, 1) === '=') {
                $operator .= substr($constraint, 1, 1);
                $versionPos++;
            }
            $requirement = substr($constraint, $versionPos);
            $satisfied = version_compare($version, $requirement, $operator);
        } elseif ($constraint === '*') {
            $satisfied = true;
        } elseif (version_compare($version, $constraint) === 0) {
            $satisfied = true;
        }

        return $satisfied;
    }


    /**
     * Get the next significant release version for a given version string.
     *
     * @param string $version A valid SemVer version string.
     *
     * @author Jason Coward
     *
     * @return string The next significant version for the specified version.
     */
    public static function nextSignificantRelease($version)
    {
        $parsed = explode('.', $version, 3);
        if (count($parsed) > 1) {
            array_pop($parsed);
        }
        $parsed[count($parsed) - 1]++;
        if (count($parsed) === 1) {
            $parsed[] = '0';
        }

        return implode('.', $parsed);
    }
}
