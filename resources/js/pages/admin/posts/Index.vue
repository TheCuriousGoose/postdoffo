<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/PostController';
import DataPagination from '@/components/admin/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { confirmDialog } from '@/lib/dialogs';
import { dashboard } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/posts';
import type { Paginated } from '@/types';

type AdminPost = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    updated_at: string;
};

defineProps<{
    posts: Paginated<AdminPost>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Posts', href: index() },
        ],
    },
});

function visit(pageNumber?: number) {
    router.get(
        index().url,
        { page: pageNumber },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['posts'],
        },
    );
}

async function deletePost(post: AdminPost) {
    const confirmed = await confirmDialog({
        title: `Delete "${post.title}"?`,
        description:
            'This permanently deletes the post. If it was published, its URL will start returning 404. This cannot be undone.',
        confirmText: 'Delete post',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroy(post.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin - Posts" />

    <Card class="gap-0 py-0">
        <CardHeader
            class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <CardTitle class="text-base">Blog posts</CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ posts.total }}
                    {{ posts.total === 1 ? 'post' : 'posts' }}, drafts included.
                </p>
            </div>

            <Button as-child size="sm">
                <Link :href="create()"> <Plus class="size-4" /> New post </Link>
            </Button>
        </CardHeader>

        <Separator />

        <CardContent class="px-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-6">Title</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Published</TableHead>
                        <TableHead class="w-10 pr-6" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="post in posts.data" :key="post.id">
                        <TableCell class="pl-6">
                            <p class="font-medium">{{ post.title }}</p>
                            <p class="text-xs text-muted-foreground">
                                /blog/{{ post.slug }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    post.is_published ? 'default' : 'secondary'
                                "
                            >
                                {{ post.is_published ? 'Published' : 'Draft' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">
                            {{ post.published_at ?? '—' }}
                        </TableCell>
                        <TableCell class="pr-6">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        aria-label="Post options"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link :href="edit(post.id)">
                                            <Pencil class="size-3.5" /> Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="deletePost(post)"
                                    >
                                        <Trash2 class="size-3.5" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div
                v-if="!posts.data.length"
                class="flex flex-col items-center gap-2 py-16 text-center"
            >
                <FileText class="size-8 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">No posts yet.</p>
            </div>
        </CardContent>

        <template v-if="posts.data.length">
            <Separator />
            <DataPagination :meta="posts" @update:page="visit" />
        </template>
    </Card>
</template>
