<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Helpers;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Class provides generalized functions useful for several component files.
 */
class Users
{
    /**
     * The logged in user.
     * @var User
     */
    private static $user;

    /**
     * Resolves the user id
     *
     * @return int the id of the user
     */
    public static function getID(): int
    {
        return (int)self::getUser()->id;
    }

    /**
     * Resolves the user id
     *
     * @return string the name of the user
     */
    public static function getName(): string
    {
        return self::getUser()->name;
    }

    /**
     * Get a user object.
     *
     * Returns the global {@link User} object, only creating it if it doesn't already exist.
     *
     * @param   int  $userID  The user to load - Can be an int or string - If string, it is converted to ID automatically.
     *
     * @return  User a user object specifically requested ids return a dynamic user, otherwise the current user
     */
    public static function getUser($userID = 0): User
    {
        // A user was specifically requested by id.
        if ($userID) {
            return User::getInstance($userID);
        }

        // A static user already exists.
        if (self::$user and self::$user->id) {
            return self::$user;
        }

        $defaultUser    = Factory::getUser();
        $userName       = Input::getString('username');
        $authentication = urldecode(Input::getString('auth'));

        // No authentication parameters => use Joomla
        if (empty($userName) or empty($authentication)) {
            self::$user = $defaultUser;

            return self::$user;
        }

        $requested = User::getInstance($userName);

        // The requested user does not exist
        if ($requested->id and password_verify($requested->email . $requested->registerDate, $authentication)) {
            self::$user = $requested;
        } else {
            self::$user = $defaultUser;
        }

        return self::$user;
    }

    /**
     * Resolves a user name attribute into forename and surname attributes.
     *
     * @param   int  $userID  the id of the user whose full name should be resolved
     *
     * @return array the first and last names of the user
     */
    public static function resolveUserName($userID = 0): array
    {
        $user           = Factory::getUser($userID);
        $sanitizedName  = trim(preg_replace('/[^A-ZÀ-ÖØ-Þa-zß-ÿ\p{N}\.\-\']/', ' ', $user->name));
        $nameFragments  = array_filter(explode(" ", $sanitizedName));
        $surname        = array_pop($nameFragments);
        $nameSupplement = '';

        // The next element is a supplementary preposition.
        while (preg_match('/^[a-zß-ÿ]+$/', end($nameFragments))) {
            $nameSupplement = array_pop($nameFragments);
            $surname        = $nameSupplement . ' ' . $surname;
        }

        // These supplements indicate the existence of a further noun.
        if (in_array($nameSupplement, ['zu', 'zum'])) {
            $otherSurname = array_pop($nameFragments);
            $surname      = $otherSurname . ' ' . $surname;

            while (preg_match('/^[a-zß-ÿ]+$/', end($nameFragments))) {
                $nameSupplement = array_pop($nameFragments);
                $surname        = $nameSupplement . ' ' . $surname;
            }
        }

        $forename = implode(" ", $nameFragments);

        return ['forename' => $forename, 'surname' => $surname];
    }
}
