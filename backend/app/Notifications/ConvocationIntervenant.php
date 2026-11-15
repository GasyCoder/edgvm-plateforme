<?php

namespace App\Notifications;

use App\Models\MembreEquipe;
use App\Models\Passage;
use App\Models\Seance;
use App\Models\User;
use App\Support\GabaritActe;
use App\Support\ReglagesApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * À un modérateur ou membre du jury : ses passages dans une séance. Par e-mail, et dans la cloche
 * s'il a un compte sur la plateforme (même adresse e-mail).
 */
class ConvocationIntervenant extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Passage>  $passages
     */
    public function __construct(public Seance $seance, public MembreEquipe $membre, public Collection $passages) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ($notifiable->actif ? ['database', 'mail'] : ['database']) : ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function titre(): string
    {
        return 'Convocation : '.$this->seance->type->libelle();
    }

    public function message(): string
    {
        $lignes = $this->passages->map(function (Passage $passage): string {
            $role = $passage->intervenants->firstWhere('id', $this->membre->id)?->pivot->role === 'jury' ? 'jury' : 'modération';

            return $passage->debut->format('H:i').'-'.$passage->fin->format('H:i').' '.$passage->doctorant->nomComplet().' ('.$role.')';
        })->implode(' ; ');

        return 'Vous intervenez à « '.$this->seance->titre.' » le '.GabaritActe::dateLongue($this->seance->date)
            .($this->seance->lieu ? ', '.$this->seance->lieu : '').' : '.$lignes.'.';
    }

    /**
     * @return array{titre: string, message: string, lien: string, categorie: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['titre' => $this->titre(), 'message' => $this->message(), 'lien' => '/seances/'.$this->seance->id, 'categorie' => 'seance'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = ReglagesApplication::nomPourCourriels();
        $message = (new MailMessage)
            ->subject($this->titre().', '.$nom)
            ->greeting('Bonjour '.$this->membre->nomAvecTitre().',')
            ->line('Vous intervenez à « '.$this->seance->titre.' » le '.GabaritActe::dateLongue($this->seance->date).($this->seance->lieu ? ', '.$this->seance->lieu : '').'.');

        foreach ($this->passages as $passage) {
            $role = $passage->intervenants->firstWhere('id', $this->membre->id)?->pivot->role === 'jury' ? 'jury' : 'modération';
            $message->line($passage->debut->format('H:i').' à '.$passage->fin->format('H:i').' : '.$passage->doctorant->nomComplet()
                .($passage->sujet ? ', « '.$passage->sujet.' »' : '').' ('.$role.')');
        }

        if ($notifiable instanceof User) {
            $message->action('Voir sur la plateforme', rtrim((string) config('app.frontend_url'), '/').'/seances/'.$this->seance->id);
        }

        return $message->salutation("L'équipe de l'{$nom}");
    }
}
