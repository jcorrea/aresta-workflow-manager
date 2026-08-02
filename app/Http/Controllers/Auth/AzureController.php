<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AzureController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // O `state` do OAuth muda a cada request — se essa resposta (302 pro Microsoft)
        // for cacheada por um proxy/CDN, tentativas seguintes reusam uma URL com state
        // velho, que nunca bate com o da sessão atual e derruba o login com
        // InvalidStateException (mesmo cuidado do GIITS Status).
        //
        // `prompt=select_account`: o logout daqui (`/logout`) só derruba a sessão do
        // Laravel, nunca a sessão SSO da própria Microsoft (cookie em
        // login.microsoftonline.com). Sem esse parâmetro, um login seguinte — seja após
        // logout explícito ou após a sessão local expirar sozinha — reautentica em
        // silêncio com a sessão SSO ainda viva, sem nunca mostrar a tela de escolha de
        // conta da Microsoft.
        return Socialite::driver('microsoft')
            ->with(['prompt' => 'select_account'])
            ->redirect()
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function callback(): RedirectResponse
    {
        $azureUser = Socialite::driver('microsoft')->user();

        // Casa primeiro por azure_id (login recorrente); se não achar, casa por e-mail
        // para aproveitar um usuário pré-cadastrado (ex.: já adicionado a uma organização
        // antes do primeiro login via SSO).
        $user = User::where('azure_id', $azureUser->getId())->first()
            ?? User::where('email', $azureUser->getEmail())->first()
            ?? new User;

        $user->fill([
            'azure_id' => $azureUser->getId(),
            'name' => $azureUser->getName() ?: $azureUser->getNickname() ?: $azureUser->getEmail(),
            'email' => $azureUser->getEmail(),
            'avatar_url' => $azureUser->getAvatar(),
        ])->save();

        Auth::login($user, remember: true);

        return redirect()->route('home');
    }
}
