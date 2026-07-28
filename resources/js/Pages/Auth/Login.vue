<script setup>

import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-950 px-6">
        <div class="card w-full max-w-md">
            <h1 class="mb-2 text-2xl font-bold">Sign in</h1>
            <p class="mb-6 text-sm text-gray-400">
                Access LedgerPay fintech dashboard.
            </p>

            <form class="space-y-4" @submit.prevent="form.post('/login')">
                <div>
                    <label class="label">Email</label>
                    <input v-model="form.email" class="input" type="email">
                    <div v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                        {{ form.errors.email }}
                    </div>
                </div>

                <div>
                    <label class="label">Password</label>
                    <input v-model="form.password" class="input" type="password">
                    <div v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                        {{ form.errors.password }}
                    </div>
                </div>

                <button class="btn w-full" :disabled="form.processing">
                    Login
                </button>
            </form>

            <div class="mt-6 text-sm text-gray-400">
                No account?
                <Link href="/register" class="text-indigo-400 hover:text-indigo-300">
                    Register
                </Link>
            </div>
        </div>
    </div>
</template>
