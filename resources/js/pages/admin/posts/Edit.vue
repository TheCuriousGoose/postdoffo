<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/Admin/PostController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/posts';

const props = defineProps<{
    post: {
        id: number;
        title: string;
        slug: string;
        excerpt: string;
        body: string;
        published: boolean;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Posts', href: index() },
        ],
    },
});

const isEditing = computed(() => props.post !== null);

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    body: props.post?.body ?? '',
    published: props.post?.published ?? false,
});

function submit() {
    if (props.post) {
        form.patch(update(props.post.id).url, { preserveScroll: true });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Admin - Edit post' : 'Admin - New post'" />

    <div class="max-w-2xl">
        <h1 class="font-display text-xl font-semibold tracking-tight">
            {{ isEditing ? 'Edit post' : 'New post' }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Write in Markdown. It's rendered to sanitized HTML on the public
            blog.
        </p>

        <Card class="mt-6">
            <CardContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" required />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            placeholder="Leave blank to generate from the title"
                        />
                        <p class="text-xs text-muted-foreground">
                            Lowercase letters, numbers and hyphens. The public
                            URL is /blog/&lt;slug&gt;.
                        </p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="excerpt">Excerpt</Label>
                        <Textarea
                            id="excerpt"
                            v-model="form.excerpt"
                            rows="2"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Shown on the blog index and used as the page's meta
                            description. Max 300 characters.
                        </p>
                        <InputError :message="form.errors.excerpt" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="body">Body (Markdown)</Label>
                        <Textarea
                            id="body"
                            v-model="form.body"
                            rows="18"
                            class="font-mono text-sm"
                            required
                        />
                        <InputError :message="form.errors.body" />
                    </div>

                    <label class="flex items-center gap-2.5">
                        <Checkbox v-model="form.published" />
                        <span class="text-sm">
                            Published
                            <span class="text-muted-foreground">
                                — unchecked keeps it as a draft, hidden from the
                                public blog.
                            </span>
                        </span>
                    </label>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">
                            {{ isEditing ? 'Save changes' : 'Create post' }}
                        </Button>
                        <Button as-child variant="ghost">
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
