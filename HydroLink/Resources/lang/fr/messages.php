<?php

return [
    'description' => 'Comptes Proton Mail gratuits dans FreeScout, via un pont hydroxide local géré par le cron.',
    'disclaimer'  => 'Projet indépendant, non affilié à Proton AG. Proton et Proton Mail sont des marques de Proton AG. API non officielle : testez d\'abord avec une adresse secondaire.',

    // statuses
    'st_ok' => 'OK', 'st_problem' => 'Problème', 'st_none' => 'Aucun', 'st_check' => 'À vérifier', 'st_missing' => 'Absent',
    'st_update' => 'Mise à jour', 'st_off' => 'Désactivé', 'st_on' => 'Activé',
    'st_alerts' => '{1} :n alerte|[2,*] :n alertes',
    'svc_running' => 'Actif', 'svc_starting' => 'Démarrage', 'svc_failed' => 'En panne', 'svc_stopped' => 'Arrêté',
    'acc_pending' => 'En attente', 'acc_running' => 'Connexion', 'acc_waiting_2fa' => 'Code 2FA', 'acc_waiting_captcha' => 'Vérification',
    'acc_needs_password' => 'Mot de passe', 'acc_connected' => 'Connecté', 'acc_error' => 'Erreur', 'acc_removing' => 'Suppression',
    'acc_paused' => 'En pause',

    // service card
    'service' => 'Service', 'cron' => 'Cron', 'cron_ok' => 'Actif', 'cron_missing' => 'Absent',
    'start' => 'Démarrer', 'stop' => 'Arrêter', 'restart' => 'Redémarrer',
    'env_details' => 'Environnement', 'env_proc_open' => 'proc_open (CLI)', 'env_exec' => 'Exécution dans storage/',
    'env_writable' => 'storage/ accessible en écriture', 'env_arch' => 'Architecture',
    'queued_start' => 'Démarrage demandé : le cron s\'en charge dans moins d\'une minute.',
    'queued_stop' => 'Arrêt demandé : le cron s\'en charge dans moins d\'une minute.',
    'queued_restart' => 'Redémarrage demandé : le cron s\'en charge dans moins d\'une minute.',

    // accounts
    'ka_title' => 'Garder en vie', 'ka_enable' => 'Garder les comptes actifs', 'ka_method' => 'Méthode',
    'ka_intro' => 'Proton peut supprimer un compte gratuit après 24 mois sans activité. HydroLink fait une vraie action sur chaque compte connecté à l\'intervalle ci-dessous.',
    'ka_m_read' => 'Lire le dernier e-mail', 'ka_m_read_hint' => 'Ouvre la boîte de réception et lit le dernier e-mail ; rien n\'est envoyé, les e-mails non lus restent non lus.',
    'ka_m_self' => 'S\'envoyer un e-mail', 'ka_m_self_hint' => 'Envoie un court e-mail au compte lui-même, le lit, puis le met à la corbeille. Non importé par FreeScout.',
    'ka_m_other' => 'Envoyer un e-mail à une autre adresse', 'ka_m_other_hint' => 'Envoie un court e-mail à l\'adresse ci-dessous, puis ouvre la boîte de réception.',
    'ka_to' => 'Autre adresse (méthode « autre adresse »)', 'ka_days' => 'Tous les (jours)',
    'ka_policy' => 'Ce que Proton compte comme activité',
    'ka_policy_text' => 'Conditions de Proton (section 3) : se connecter sur les applications web, ordinateur ou mobile de Proton, ou, une fois connecté, utiliser un produit (ouvrir la boîte de réception, lire un e-mail, se connecter à un serveur VPN). Compté par compte, pas par adresse ; les offres payantes ne sont jamais supprimées pour inactivité ; des rappels partent vers l\'adresse de récupération 30, 15 et 7 jours avant. Proton ne dit pas si les clients tiers comme hydroxide comptent : se connecter une fois par an sur proton.me reste le seul moyen certain.',
    'ka_last' => 'Maintenu en vie', 'ka_never' => 'pas encore', 'ka_now' => 'Maintenant',
    'ka_queued' => 'Maintien en vie demandé : fait au prochain passage du cron (dans la minute).',
    'ka_why_not_connected' => 'Seulement pour un compte connecté.', 'ka_st_no_address' => 'Pas d\'adresse',
    'rename' => 'Renommer', 'name' => 'Nom', 'save' => 'Enregistrer', 'renamed' => 'Nom enregistré.', 'mailbox_link' => 'Boîte FreeScout',
    'rename_hint' => 'Affiché sur cette carte et donné à la boîte FreeScout (40 caractères au plus). Vide : nom par défaut.',
    'name_too_long' => 'Le nom est limité à 40 caractères.', 'name_invalid' => 'Le nom contient des caractères non autorisés.',
    'accounts' => 'Comptes', 'settings_title' => 'Réglages',
    'add_account' => 'Ajouter un compte', 'address' => 'Adresse', 'password' => 'Mot de passe',
    'two_passwords' => 'Compte à deux mots de passe', 'mailbox_password' => 'Mot de passe de boîte',
    'connect' => 'Connecter', 'add_hint' => 'Le mot de passe sert une seule fois et n\'est pas conservé par HydroLink.',
    'step_pending' => 'En attente du cron (moins d\'une minute)', 'step_running' => 'Connexion à Proton en cours',
    'totp' => 'Code 2FA', 'send' => 'Envoyer',
    'captcha_step' => 'Proton demande une vérification humaine. Ouvrez-la, terminez-la, puis confirmez ici.',
    'captcha_open' => 'Ouvrir la vérification', 'captcha_done' => 'J\'ai terminé la vérification',
    'reconnect' => 'Reconnecter', 'pause' => 'Mettre en pause', 'resume' => 'Reprendre',
    'delete' => 'Supprimer', 'delete_confirm' => 'Confirmer la suppression',
    'delete_hint' => 'La boîte FreeScout et ses tickets sont conservés, mais elle n\'utilise plus ce compte.',
    'logs' => 'Journaux', 'loading' => 'Chargement...',
    'login_queued' => 'Connexion demandée : elle démarre dans moins d\'une minute.',
    'code_sent' => 'Code transmis.', 'verification_sent' => 'Vérification signalée, la connexion continue.',
    'paused' => 'Compte en pause : FreeScout ne relève plus cette boîte.', 'resumed' => 'Relève reprise.',
    'removed' => 'Compte supprimé.', 'confirm_needed' => 'Confirmation manquante.',
    'email_is_user' => 'Cette adresse est celle d\'un utilisateur FreeScout : une boîte ne peut pas avoir la même.',
    'why_not_connected' => 'Disponible une fois le compte connecté.', 'why_busy' => 'Une connexion est déjà en cours.',
    'why_no_2fa' => 'Aucun code 2FA n\'est attendu pour ce compte.', 'why_no_captcha' => 'Aucune vérification n\'est attendue.',
    'why_no_binary' => 'Installez d\'abord le binaire (carte Binaire).', 'why_no_account' => 'Ajoutez d\'abord un compte.',

    // binary
    'binary' => 'Binaire', 'installed' => 'Installé', 'available' => 'Disponible',
    'install' => 'Installer', 'update' => 'Mettre à jour', 'auto_update' => 'Mise à jour automatique',
    'why_queued' => 'Demande envoyée : le cron s\'en charge dans moins d\'une minute.',
    'queued_binary' => 'Mise à jour demandée : le cron s\'en charge dans moins d\'une minute.',
    'vt_waiting' => 'Analyse VirusTotal en cours, la mise à jour reprendra seule.',
    'vt_step_on' => 'Chaque nouveau binaire : SHA-256, analyse VirusTotal, test, puis retour arrière en cas d\'échec.',
    'vt_step_off' => 'Chaque nouveau binaire : SHA-256, test, puis retour arrière en cas d\'échec. Analyse VirusTotal sautée (pas de clé).',

    // notifications
    'notifications' => 'Notifications', 'channels' => 'Canaux', 'channel_mail' => 'E-mail d\'alerte FreeScout',
    'reminder' => 'Rappel (heures, 0 = jamais)', 'notify_recovery' => 'Prévenir du retour à la normale',
    'active_alerts' => 'Alertes en cours', 'test' => 'Tester',
    'test_sent' => '{1} Notification de test envoyée sur :n canal.|[2,*] Notification de test envoyée sur :n canaux.',
    'test_none' => 'Aucun canal choisi ou disponible.',
    'notify_problem' => 'HydroLink : :code :title', 'notify_reminder' => 'HydroLink (rappel) : :code :title',
    'notify_recovered' => 'HydroLink : retour à la normale (:code)', 'notify_test' => 'HydroLink : test',
    'notify_recovered_text' => 'Le problème :code (:title) est résolu.', 'notify_test_text' => 'Les notifications de HydroLink arrivent bien sur ce canal.',

    // VirusTotal
    'vt_key' => 'Clé API', 'vt_key_set' => 'enregistrée', 'optional' => 'Facultatif',
    'vt_threshold' => 'Seuil de détection', 'vt_remove' => 'Retirer la clé',
    'vt_hint' => 'Au-dessus du seuil, le binaire est mis en quarantaine et la mise à jour annulée. Quota gratuit respecté.',
    'vt_no_key' => 'Aucune clé enregistrée.', 'vt_valid' => 'Clé valide.', 'vt_refused' => 'Clé refusée par VirusTotal.',
    'vt_quota' => 'Quota VirusTotal atteint, réessayez plus tard.', 'vt_error' => 'VirusTotal ne répond pas comme prévu.',

    // errors and generic
    'err_expected' => 'Attendu', 'err_effect' => 'Effet', 'err_action' => 'Action', 'err_detail' => 'Détail',
    'saved' => 'Enregistré.', 'request_failed' => 'La demande a échoué.', 'bad_request' => 'Demande invalide.',
];
