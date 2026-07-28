<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-950 px-6">
        <div class="card w-full max-w-md">
            <h1 class="mb-2 text-2xl font-bold">Create account</h1>
            <p class="mb-6 text-sm text-gray-400">
                Registration creates both User and Customer profile.
            </p>

            <form class="space-y-4" @submit.prevent="form.post('/register')">
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input" type="text">
                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-400">
                        {{ form.errors.name }}
                    </div>
                </div>

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
                    Register
                </button>
            </form>

            <div class="mt-6 text-sm text-gray-400">
                Already registered?
                <Link href="/login" class="text-indigo-400 hover:text-indigo-300">
                    Login
                </Link>
            </div>
        </div>
    </div>
</template>
