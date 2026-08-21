<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Users } from '@lucide/vue';
import { show, store } from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { promptDialog } from '@/lib/dialogs';
import { index } from '@/routes/teams';
import type { Team } from '@/types/team';

defineProps<{
    teams: Team[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teams', href: index() }],
    },
});

async function createTeam() {
    const name = await promptDialog({
        title: 'New team',
        label: 'Team name',
        defaultValue: 'My Team',
        confirmText: 'Create',
    });

    if (!name) {
        return;
    }

    router.post(store().url, { name });
}
</script>

<template>
    <Head title="Teams" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Teams"
                description="A team owns workspaces — anyone who joins gets access to all of them."
            />
            <Button class="w-full sm:w-auto" @click="createTeam">
                <Plus class="size-4" />
                New team
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="team in teams"
                :key="team.id"
                class="group cursor-pointer transition-colors hover:border-primary"
                @click="router.visit(show(team).url)"
            >
                <CardHeader class="flex flex-row items-start justify-between gap-2">
                    <CardTitle class="truncate">{{ team.name }}</CardTitle>
                    <Users class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ team.workspaces_count ?? 0 }} workspaces
                </CardContent>
            </Card>

            <p v-if="!teams.length" class="text-sm text-muted-foreground">
                No teams yet — create one to share workspaces with everyone in it.
            </p>
        </div>
    </div>
</template>
