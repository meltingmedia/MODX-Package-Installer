<?php namespace Melting\MODX\Package;

/**
 * An utility class to help "validate" dependencies versions
 *
 * Methods are "extracted" from MODX Revolution 2.4 and have been created by Jason Coward <https://github.com/opengeek>
 */
class Checker
{
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
