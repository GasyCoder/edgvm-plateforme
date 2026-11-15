<?php

namespace App\Http\Controllers\Api;

use App\Actions\EnvoyerInvitation;
use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompteRequest;
use App\Http\Resources\CompteResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comptes de l'administration et des membres d'équipe d'accueil.
 */
class CompteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);
        $terme = $request->string('recherche')->trim()->toString();

        $comptes = User::query()
            ->with(['equipesAccueil', 'doctorant'])
            ->when(
                $request->string('role')->toString(),
                fn ($requete, string $role) => $requete->where('role', $role),
                fn ($requete) => $requete->where('role', '!=', RoleUtilisateur::Doctorant->value),
            )
            ->when($terme !== '', fn ($requete) => $requete->where(fn ($sous) => $sous->where('name', 'like', "%{$terme}%")->orWhere('email', 'like', "%{$terme}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return CompteResource::collection($comptes);
    }

    public function store(CompteRequest $request, EnvoyerInvitation $envoyerInvitation): JsonResponse
    {
        $compte = DB::transaction(function () use ($request): User {
            $compte = User::query()->create([
                ...$request->safe()->only(['name', 'email', 'telephone', 'role']),
                'actif' => $request->boolean('actif', true),
                'password' => Str::password(32),
            ]);
            $this->synchroniserEquipes($compte, $request);

            return $compte;
        });

        if ($request->boolean('envoyer_invitation', true)) {
            $envoyerInvitation($compte);
        }

        return (new CompteResource($compte->load(['equipesAccueil', 'doctorant'])))->response()->setStatusCode(201);
    }

    public function update(CompteRequest $request, User $compte): CompteResource
    {
        DB::transaction(function () use ($request, $compte): void {
            $compte->update($request->safe()->only(['name', 'email', 'telephone', 'role', 'actif']));

            if (! $compte->estDoctorant()) {
                $this->synchroniserEquipes($compte, $request);
            }
        });

        return new CompteResource($compte->refresh()->load(['equipesAccueil', 'doctorant']));
    }

    /**
     * Renvoie le lien de premier accès (compte jamais utilisé ou mot de passe perdu).
     */
    public function lienAcces(User $compte, EnvoyerInvitation $envoyerInvitation): JsonResponse
    {
        $this->authorize('update', $compte);

        $envoyerInvitation($compte);

        return response()->json(['message' => "Lien d'accès envoyé à {$compte->email}."]);
    }

    private function synchroniserEquipes(User $compte, CompteRequest $request): void
    {
        $equipes = $compte->estMembreEad() ? $request->validated('equipes', []) : [];

        $compte->equipesAccueil()->sync(collect($equipes)->mapWithKeys(fn (int $id) => [$id => ['fonction' => 'membre']])->all());
    }
}
