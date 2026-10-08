<template>
    <main class="auth-surface flex min-h-dvh flex-col items-center justify-center px-5 py-12">
        <div class="mb-9"><slot name="logo" /></div>
        <div class="w-full max-w-md rounded-3xl border border-base-300 bg-base-100 p-7 shadow-xl shadow-slate-900/5 sm:p-9">
            <slot />
        </div>
        <p class="mt-8 text-xs tracking-wide text-base-content/50">A private place for what matters.</p>
    </main>
</template>
