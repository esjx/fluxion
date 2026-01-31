<?php
namespace Fluxion\Auth;

use Exception;
use Fluxion\ImageManipulate;

class LdapCaixa extends LdapIisCaixa
{

    public function saveUserImage($login)
    {

        $base_dir = $_ENV['LOCAL_UPLOAD'] ?? '';

        $file = strtolower($this->_user_image_dir . $login . '.jpg');

        try {

            $lc = @ldap_connect('ldaps://corp.caixa.gov.br:3269');
            ldap_set_option($lc, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($lc, LDAP_OPT_REFERRALS, 0);

            if ($lc && @ldap_bind($lc, 'corpcaixa\\' . $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {

                $ls = ldap_search($lc, 'OU=caixa,DC=corp,DC=caixa,DC=gov,DC=br', "(samaccountname=$login)");
                $lge = ldap_get_entries($lc, $ls);

                if (isset($lge[0]) && isset($lge[0]['thumbnailphoto']) && isset($lge[0]['thumbnailphoto'][0]))
                    ImageManipulate::createThumbFromString($lge[0]['thumbnailphoto'][0], $base_dir . $file);

            }

        } catch (Exception $e) {

        }

    }

}
