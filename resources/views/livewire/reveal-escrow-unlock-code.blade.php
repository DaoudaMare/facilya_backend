<div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Identifiant escrow (commerçant)</p>
            <p class="mt-1 text-2xl font-bold tracking-[0.25em] text-gray-950 dark:text-white">
                {{ $publicId ?: '—' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">6 chiffres transmis au commerçant. Ce n’est pas le code de déblocage.</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Code de déblocage des fonds</p>
            <p class="mt-1 text-2xl font-bold tracking-[0.25em] text-gray-950 dark:text-white">
                {{ $unlockCode ?: '••••••' }}
            </p>
            @unless ($canReveal)
                <p class="mt-1 text-xs text-gray-500">Masqué. Permission « Voir les codes de déblocage escrow » requise.</p>
            @endunless
            <div class="mt-3 flex flex-wrap gap-2">
                {{ $this->revealAction }}
                {{ $this->hideAction }}
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
