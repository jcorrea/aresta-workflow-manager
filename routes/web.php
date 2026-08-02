<?php

use App\Http\Controllers\Auth\AzureController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProcessInstanceActivityController;
use App\Http\Controllers\ProcessInstanceController;
use App\Http\Controllers\WorkflowActivityController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowStepController;
use App\Http\Controllers\WorkflowTransitionController;
use App\Http\Controllers\WorkflowVersionController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('home');
    }

    return Inertia::render('Auth/Login', [
        'appName' => config('app.name'),
        'azureRedirectUrl' => route('azure.redirect'),
    ]);
})->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    // Inertia::location força um redirecionamento completo do browser,
    // necessário para sair do contexto SPA e limpar o estado da sessão.
    return Inertia::location(route('login'));
})->name('logout');

Route::get('/auth/azure/redirect', [AzureController::class, 'redirect'])->name('azure.redirect');
Route::get('/auth/azure/callback', [AzureController::class, 'callback'])->name('azure.callback');

// TEMPORÁRIO — só pra testar a UI num ambiente local sem App Registration Azure real
// configurado (AZURE_CLIENT_ID/SECRET vazios). Remover antes de qualquer deploy real; nunca
// habilitado fora de app()->environment('local').
if (app()->environment('local')) {
    Route::get('/dev-login', function () {
        $user = User::where('email', request('email', 'admin@demo.test'))->firstOrFail();
        Auth::login($user);

        return redirect()->route('home');
    })->name('dev-login');
}

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        $user = request()->user()->load('organizations');
        return Inertia::render('Home', [
            'appName' => config('app.name'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
            'organizations' => $user->organizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
            ]),
        ]);
    })->name('home');

    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
    Route::get('/workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
    Route::post('/workflows/{workflow}/rollback', [WorkflowController::class, 'rollback'])->name('workflows.rollback');

    Route::post('/workflows/{workflow}/versions', [WorkflowVersionController::class, 'store'])->name('workflows.versions.store');
    Route::get('/workflows/{workflow}/versions/{version}/edit', [WorkflowVersionController::class, 'edit'])->name('workflows.versions.edit');
    Route::post('/workflows/{workflow}/versions/{version}/publish', [WorkflowVersionController::class, 'publish'])->name('workflows.versions.publish');
    Route::post('/workflows/{workflow}/versions/{version}/refine-ai', [WorkflowVersionController::class, 'refineWithAi'])->name('workflows.versions.refine-ai');

    Route::post('/workflows/{workflow}/versions/{version}/steps', [WorkflowStepController::class, 'store'])->name('workflow-steps.store');
    Route::patch('/workflow-steps/{step}', [WorkflowStepController::class, 'update'])->name('workflow-steps.update');
    Route::delete('/workflow-steps/{step}', [WorkflowStepController::class, 'destroy'])->name('workflow-steps.destroy');

    Route::post('/workflows/{workflow}/versions/{version}/activities', [WorkflowActivityController::class, 'store'])->name('workflow-activities.store');
    Route::patch('/workflow-activities/{activity}', [WorkflowActivityController::class, 'update'])->name('workflow-activities.update');
    Route::delete('/workflow-activities/{activity}', [WorkflowActivityController::class, 'destroy'])->name('workflow-activities.destroy');

    Route::post('/workflows/{workflow}/versions/{version}/transitions', [WorkflowTransitionController::class, 'store'])->name('workflow-transitions.store');
    Route::patch('/workflow-transitions/{transition}', [WorkflowTransitionController::class, 'update'])->name('workflow-transitions.update');
    Route::delete('/workflow-transitions/{transition}', [WorkflowTransitionController::class, 'destroy'])->name('workflow-transitions.destroy');

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/activities/{activity}/claim', [ProcessInstanceActivityController::class, 'claim'])->name('process-instance-activities.claim');
    Route::post('/inbox/activities/{activity}/complete', [ProcessInstanceActivityController::class, 'complete'])->name('process-instance-activities.complete');

    Route::get('/instances', [ProcessInstanceController::class, 'index'])->name('process-instances.index');
    Route::get('/instances/{instance:code}', [ProcessInstanceController::class, 'show'])->name('process-instances.show');
});
