---
name: edgvm-notifications
description: Envoyer des notifications dans la plateforme EDGVM (rappel de paiement, information aux doctorants ou aux membres des équipes d'accueil, acte disponible, échéance de suivi) par les canaux plateforme, e-mail, SMS et WhatsApp. À utiliser pour créer une notification, une diffusion à un groupe, un rappel planifié ou un nouveau canal d'envoi.
---

# Notifications (backend/)

## Principes

- Une classe par notification dans `app/Notifications/`, par exemple `RappelPaiement`, `ActeDisponible`, `InformationEcole` ou `EcheanceSuivi`.
- Chaque notification implémente `ShouldQueue`. L'envoi passe par la file d'attente, avec la connexion `database` et un worker lancé par cron.
- La méthode `via()` choisit les canaux selon :
  1. ce que la notification permet ;
  2. les préférences de l'utilisateur ;
  3. les coordonnées disponibles : pas de SMS sans `telephone`, pas de WhatsApp sans accord.
- Canaux :
  - `database` : toujours actif. C'est la cloche dans la plateforme.
  - `mail` : utilise le SMTP configuré dans `.env`.
  - `sms` et `whatsapp` : canaux maison, voir ci-dessous.
- Textes en français, courts. Aucun tiret cadratin ou demi-cadratin. Le SMS tient en 160 caractères et ne contient pas de lien sensible.

## Canaux SMS et WhatsApp

Le fournisseur n'est pas encore choisi. Le code ne doit pas en dépendre.

- Interface `App\Notifications\Canaux\Contrats\EnvoiSms`, avec la méthode `envoyer(string $numeroE164, string $texte): void`. Prévoir l'équivalent `EnvoiWhatsApp`, avec un modèle de message et des paramètres.
- Pilotes dans `App\Notifications\Canaux\Pilotes\` :
  - `JournalSms` : écrit dans le log. C'est le pilote par défaut en local et dans les tests.
  - les pilotes réels viendront plus tard.
- Choix du pilote :
  - `config/services.php` : `services.sms.pilote` et `services.whatsapp.pilote`, lus depuis `.env` ;
  - liaison dans un service provider.
- Les classes de canal `CanalSms` et `CanalWhatsApp` appellent `$notification->toSms($notifiable)` ou `toWhatsApp()`.
- WhatsApp Business n'accepte, hors conversation ouverte, que des **modèles de message validés**. Chaque notification WhatsApp référence un nom de modèle.
- Aucun envoi réel hors production. Les tests utilisent `Notification::fake()`.

## Diffusions (un message à un groupe)

- Table `diffusions` :
  - `titre`, `message` et `canaux` (json) ;
  - `cible` : `tous_doctorants`, `niveau`, `equipe_accueil`, `en_retard_paiement` ou `membres_ead` ;
  - `parametres_cible` (json), `envoyee_par`, `envoyee_le`.
- L'envoi passe par un job, `EnvoyerDiffusion`, qui découpe les destinataires (`chunkById`) et notifie avec `InformationEcole`.
- Seul le `super_admin` crée une diffusion. Il voit le nombre de destinataires avant de confirmer.

## Rappels planifiés

- Planification dans `routes/console.php`, avec `Schedule::command(...)->dailyAt('08:00')`.
- `rappels:paiements` :
  - cible les inscriptions de l'année courante dont le reste à payer est supérieur à 0 et l'échéance dépassée ;
  - laisse au moins 7 jours entre deux rappels au même doctorant (`dernier_rappel_le`).
- `rappels:suivis` : cible les comités de suivi à venir dans les 14 jours.
- Les commandes sont idempotentes : les relancer le même jour n'envoie rien de plus.

## Tests

```php
it('reminds students who still owe money, once a week at most', function (): void {
    Notification::fake();
    $enRetard = Inscription::factory()->enRetard()->create();
    $aJour = Inscription::factory()->soldee()->create();

    $this->artisan('rappels:paiements')->assertSuccessful();
    $this->artisan('rappels:paiements')->assertSuccessful();

    Notification::assertSentToTimes($enRetard->doctorant->user, RappelPaiement::class, 1);
    Notification::assertNothingSentTo($aJour->doctorant->user);
});
```

Couvrir aussi les cas suivants :

- `via()` sans téléphone : pas de SMS ;
- le pilote `JournalSms` écrit bien dans le log ;
- un doctorant ne voit que ses propres notifications ;
- le marquage comme lu.
