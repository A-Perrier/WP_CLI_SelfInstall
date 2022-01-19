<?php


/**
 * A simple tool allowing bash users to create and configure a new WordPress application
 * Supposes you are on a Linux system and have WP_CLI globally installed on your computer
 * @author <perrier_anthony@live.fr>
 */
class WP_CLI_SelfInstall 
{
    private const WP_CORE_DL_COMMAND = 'wp core download ';
    private const WP_CONFIG_COMMAND = 'wp config create ';
    private const WP_CORE_INSTALL_COMMAND = 'wp core install ';
    private static $config_mode;
    private static $install_path;
    private static $locale = 'fr_FR';
    private static $db_name;
    private static $db_user;
    private static $db_pass;
    private static $site_url;
    private static $site_title = "Mon site WordPress installé avec WP_CLI_SelfInstall !";
    private static $admin_user;
    private static $admin_pass;
    private static $admin_email;

    public static function process ()
    {
        echo "\nBienvenue dans l'assistant de configuration WordPress !\n";
        echo "Pour installer WordPress correctement, nous allons devoir répondre à quelques questions.\n\n";
    
        self::installWordPressOnly();
    }


    /** 
     * <! Inutilisée pour le moment !>
     * Récupère le mode de configuration demandé par l'utilisateur.ice
     */
    private static function askConfigMode (): void
    {
        echo "Tout d'abord, choisissez le mode d'installation : [Par défaut : 1]\n";
        echo " 1 - Créer et installer WordPress uniquement\n";
        echo " 2 - Créer et installer WordPress et générer une base de données MySQL\n";
        $mode = readline(">>> ");

        if (strlen($mode) === 0) {
           $mode = "1";
        }

        if (trim($mode) !== "1" && trim($mode) !== "2") {
            echo "\n\n   Warning /!\ $mode n'est pas une valeur correcte.\n\n\n";
            self::askConfigMode();
            return;
        }

        self::$config_mode = $mode;
    }


    /**
     * Dirige le mode de configuration d'installation de WordPress seul
     */
    private static function installWordPressOnly ()
    {
        echo "\n Vous avez choisi l'installation de WordPress uniquement. Voyons ensemble comment créer tout ça\n";
        
        // Récupération des données pour la commande `wp core download`
        self::getInstallPath();

        // Récupération des données pour la commande `wp config`
        self::getDbName();
        self::getDbUser();
        self::getDbPass();

        // Récupération des données pour la commande `wp core install`
        self::getSiteUrl();
        self::getSiteTitle();
        self::getAdminUser();
        self::getAdminPass();
        self::getAdminEmail();

        // Creation de la base de données
        shell_exec("mysql -h localhost -u " . self::$db_user . " -p" . self::$db_pass . " -e 'CREATE DATABASE ". self::$db_name . "'");

        // Ouverture des droits aux fichiers pour contourner le bug du wp-config manquant
        if (self::$install_path) {
            shell_exec("cd " . self::$install_path);
        }
        shell_exec("sudo chmod -R 777 .*");


        // Envoi des requêtes par la WP_CLI
        self::downloadWPCore();
        self::configureWP();
        self::installWPCore();

        // On confirme le succès des opérations
        echo "\n\n\n Félicitations ! WordPress est correctement installé et la base de données configurée ! Si vous avez déjà créé votre Virtual Host et rechargé Apache, rendez-vous sur http://" . self::$site_url . " !\n\n\n";
    }


    private static function getInstallPath (): void
    {
        echo " Dans quel dossier souhaitez-vous installer votre projet ? [Par défaut : dossier courant]\n";
        $path = readline(">>> ");

        if (strlen(trim($path)) !== 0) {
            self::$install_path = trim($path);
        }
    }



    private static function getDbName (): void
    {
        echo "\n Comment souhaitez-vous nommer votre base de données ?\n";
        $dbname = readline(">>> ");

        if (strlen(trim($dbname)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom de base de données.\n\n\n";
            self::getDbName();
            return;
        }

        self::$db_name = $dbname;
    }


    private static function getDbUser (): void
    {
        echo "\n Quel est le nom utilisateur lié à votre base de données ?\n";
        $dbuser = readline(">>> ");

        if (strlen(trim($dbuser)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom d'utilisateur.\n\n\n";
            self::getDbUser();
            return;
        }

        self::$db_user = $dbuser;
    }


    private static function getDbPass (): void
    {
        echo "\n Quel est le mot de passe d'accès à votre base de données ?\n";
        $dbpass = readline_hidden(">>> ");

        self::$db_pass = $dbpass;
    }

    
    private static function getSiteUrl (): void
    {
        echo "\n Quel est l'URL de votre site ? (doit être la même que celle du VirtualHost)\n";
        $url = readline(">>> ");

        if (strlen(trim($url)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir une URL pour votre site.\n\n\n";
            self::getSiteUrl();
            return;
        }

        self::$site_url = $url;
    }


    private static function getSiteTitle (): void
    {
        echo "\n Quel est le titre de votre site ? [Par défaut: " . self::$site_title . "]\n";
        $title = readline(">>> ");

        if (strlen(trim($title)) === 0) {
            return;
        }

        self::$site_title = $title;
    }

    
    private static function getAdminUser (): void
    {
        echo "\n Quel est le nom d'utilisateur que vous souhaitez utiliser pour vous connecter ?\n";
        $username = readline(">>> ");

        if (strlen(trim($username)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom d'utilisateur pour votre site.\n\n\n";
            self::getAdminUser();
            return;
        }

        self::$admin_user = $username;
    }


    private static function getAdminPass (): void
    {
        echo "\n Quel est le mot de passe avec lequel vous souhaitez pouvoir vous connecter ?\n";
        $pass = readline_hidden(">>> ");

        if (strlen(trim($pass)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un mot de passe pour votre compte WordPress !\n\n\n";
            self::getAdminPass();
            return;
        }

        self::$admin_pass = $pass;
    }


    private static function getAdminEmail (): void
    {
        echo "\n Quel est l'email qui sera lié à votre site ?\n";
        $email = readline(">>> ");

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "\n\n   Warning /!\ $email n'est pas une adresse email valide.\n\n\n";
            self::getAdminEmail();
            return;
        }

        if (strlen(trim($email)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir une adresse email à lier à votre site.\n\n\n";
            self::getAdminEmail();
            return;
        }

        self::$admin_email = $email;
    }


    private static function downloadWPCore (): void
    {
        $command = self::$install_path === null ? self::WP_CORE_DL_COMMAND : self::WP_CORE_DL_COMMAND . '--path=' . self::$install_path;
        shell_exec($command);
    }


    private static function configureWP (): void
    {
        $command = 
            self::WP_CONFIG_COMMAND . 
            ' --locale=' . self::$locale . 
            ' --dbname=' . self::$db_name .
            ' --dbuser=' . self::$db_user .
            ' --dbpass=' . self::$db_pass . 
            ' --prompt=' . self::$db_pass
        ;
        shell_exec($command);
    }


    private static function installWPCore (): void
    {
        
        $command = 
        self::WP_CORE_INSTALL_COMMAND . 
        ' --url="'               . self::$site_url   .
        '" --title="'             . self::$site_title .
        '" --admin_user='        . self::$admin_user .
        ' --admin_password='    . self::$admin_pass . 
        ' --admin_email='       . self::$admin_email
        ;
        shell_exec($command);
    }
}


function readline_hidden ($prompt) {
    echo $prompt;
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    return $password;
}



WP_CLI_SelfInstall::process();