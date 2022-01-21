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
    private $config_mode;
    private $install_path;
    private $locale = 'fr_FR';
    private $db_name;
    private $db_user;
    private $db_pass;
    private $site_url;
    private $site_title = "Mon site WordPress installé avec WP_CLI_SelfInstall !";
    private $admin_user;
    private $admin_pass;
    private $admin_email;

    public function __construct()
    {
        $this->process();
    }

    private function process ()
    {
        echo "\nBienvenue dans l'assistant de configuration WordPress !\n";
        echo "Pour installer WordPress correctement, nous allons devoir répondre à quelques questions.\n\n";
    
        $this->installWordPressFully();
    }


    /** 
     * <! Inutilisée pour le moment !>
     * Récupère le mode de configuration demandé par l'utilisateur.ice
     */
    private function askConfigMode (): void
    {
        echo "Tout d'abord, choisissez le mode d'installation : [Par défaut : 2]\n";
        echo " 1 - Créer et installer WordPress uniquement\n";
        echo " 2 - Créer et installer WordPress et générer une base de données MySQL\n";
        $mode = readline(">>> ");

        if (strlen($mode) === 0) {
           $mode = "2";
        }

        if (trim($mode) !== "1" && trim($mode) !== "2") {
            echo "\n\n   Warning /!\ $mode n'est pas une valeur correcte.\n\n\n";
            $this->askConfigMode();
            return;
        }

        $this->config_mode = $mode;
    }


    /**
     * Dirige le mode de configuration d'installation de WordPress seul
     */
    private function installWordPressFully ()
    {
        echo "\n Vous avez choisi l'installation de WordPress uniquement. Voyons ensemble comment créer tout ça\n";
        
        // Récupération des données pour la commande `wp core download`
        $this->getInstallPath();

        // Récupération des données pour la commande `wp config`
        $this->getDbName();
        $this->getDbUser();
        $this->getDbPass();

        // Récupération des données pour la commande `wp core install`
        $this->getSiteUrl();
        $this->getSiteTitle();
        $this->getAdminUser();
        $this->getAdminPass();
        $this->getAdminEmail();

        // Création de la base de données et récupération des droits via un compte MySQL
        $this->createDatabaseAndNewUser();
        

        // Création du Virtual Host
        $this->createVirtualHost();
        $this->registerVirtualHost();

        
        if ($this->install_path) {
            shell_exec("cd " . $this->install_path);
        }

        // Ouverture des droits aux fichiers pour contourner le bug du wp-config manquant
        # $this->upgradeRights();

        // Envoi des requêtes par la WP_CLI
        $this->downloadWPCore();
        $this->configureWP();
        $this->installWPCore();

        // On confirme le succès des opérations
        echo "\n\n\n Félicitations ! WordPress est correctement installé et la base de données configurée ! Si vous avez déjà créé votre Virtual Host et rechargé Apache, rendez-vous sur http://" . $this->site_url . " !\n\n\n";
    }


    private function getInstallPath (): void
    {
        echo " Dans quel dossier souhaitez-vous installer votre projet ? [Par défaut : dossier courant]\n";
        $path = readline(">>> ");

        if (strlen(trim($path)) !== 0) {
            $this->install_path = trim($path);
        }
    }



    private function getDbName (): void
    {
        echo "\n Comment souhaitez-vous nommer votre base de données ?\n";
        $dbname = readline(">>> ");
        
        if (strlen(trim($dbname)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom de base de données.\n\n\n";
            $this->getDbName();
            return;
        }

        $this->db_name = $dbname;
    }


    private function getDbUser (): void
    {
        echo "\n Quel est le nom utilisateur lié à votre base de données ?\n";
        $dbuser = readline(">>> ");
        
        if (strlen(trim($dbuser)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom d'utilisateur.\n\n\n";
            $this->getDbUser();
            return;
        }

        $this->db_user = $dbuser;
    }


    private function getDbPass (): void
    {
        echo "\n Quel est le mot de passe d'accès à votre base de données ?\n";
        $dbpass = readline_hidden(">>> ");
        
        $this->db_pass = $dbpass;
    }

    
    private function getSiteUrl (): void
    {
        echo "\n Quel est l'URL de votre site ? (doit être la même que celle du VirtualHost)\n";
        $url = readline(">>> ");
        
        if (strlen(trim($url)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir une URL pour votre site.\n\n\n";
            $this->getSiteUrl();
            return;
        }

        $this->site_url = $url;
    }


    private function getSiteTitle (): void
    {
        echo "\n Quel est le titre de votre site ? [Par défaut: " . $this->site_title . "]\n";
        $title = readline(">>> ");

        if (strlen(trim($title)) === 0) {
            return;
        }

        $this->site_title = $title;
    }

    
    private function getAdminUser (): void
    {
        echo "\n Quel est le nom d'utilisateur que vous souhaitez utiliser pour vous connecter ?\n";
        $username = readline(">>> ");
        
        if (strlen(trim($username)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un nom d'utilisateur pour votre site.\n\n\n";
            $this->getAdminUser();
            return;
        }

        $this->admin_user = $username;
    }


    private function getAdminPass (): void
    {
        echo "\n Quel est le mot de passe avec lequel vous souhaitez pouvoir vous connecter ?\n";
        $pass = readline_hidden(">>> ");
        
        if (strlen(trim($pass)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir un mot de passe pour votre compte WordPress !\n\n\n";
            $this->getAdminPass();
            return;
        }

        $this->admin_pass = $pass;
    }


    private function getAdminEmail (): void
    {
        echo "\n Quel est l'email qui sera lié à votre site ?\n";
        $email = readline(">>> ");
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "\n\n   Warning /!\ $email n'est pas une adresse email valide.\n\n\n";
            $this->getAdminEmail();
            return;
        }

        if (strlen(trim($email)) === 0) {
            echo "\n\n   Warning /!\ Vous devez avoir une adresse email à lier à votre site.\n\n\n";
            $this->getAdminEmail();
            return;
        }

        $this->admin_email = $email;
    }

    
    private function createDatabaseAndNewUser (): void
    {
        shell_exec("sudo mysql -h localhost -u {$this->db_user} -p{$this->db_pass} -e \"DROP DATABASE IF EXISTS {$this->db_name}; CREATE DATABASE {$this->db_name}; GRANT ALL PRIVILEGES ON {$this->db_name}.* TO '{$this->db_name}'@'localhost' IDENTIFIED BY '{$this->db_name}';\"");
    }


    private function upgradeRights (): void
    {
        shell_exec("sudo chgrp -R www-data");
        shell_exec("sudo find . -type f -exec chmod 664 {} +");
        shell_exec("sudo find . -type d -exec chmod 775 {} +");
    }


    private function createVirtualHost (): void
    {

    }


    private function registerVirtualHost (): void
    {

    }


    private function downloadWPCore (): void
    {
        var_dump('ici');
        $command = $this->install_path === null ? 
            self::WP_CORE_DL_COMMAND . ' --locale=' . $this->locale :
            self::WP_CORE_DL_COMMAND . ' --locale=' . $this->locale .  ' --path=' . $this->install_path;
        shell_exec($command);
    }


    private function configureWP (): void
    {
        $command = 
            self::WP_CONFIG_COMMAND . 
            ' --dbname=' . $this->db_name .
            ' --dbuser=' . $this->db_user .
            ' --dbpass=' . $this->db_pass . 
            ' --prompt=' . $this->db_pass
        ;
        shell_exec($command);
    }


    private function installWPCore (): void
    {
        
        $command = 
        self::WP_CORE_INSTALL_COMMAND . 
        ' --url="'               . $this->site_url   .
        '" --title="'            . $this->site_title .
        '" --admin_user='        . $this->admin_user .
        ' --admin_password='     . $this->admin_pass . 
        ' --admin_email='        . $this->admin_email
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



(new WP_CLI_SelfInstall);