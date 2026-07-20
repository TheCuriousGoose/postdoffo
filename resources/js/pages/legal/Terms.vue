<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/layouts/legal/LegalLayout.vue';

const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'accounts', label: 'Your account' },
    { id: 'acceptable-use', label: 'Acceptable use' },
    { id: 'your-content', label: 'Your content' },
    { id: 'workspaces-teams', label: 'Workspaces & teams' },
    { id: 'availability', label: 'Availability' },
    { id: 'termination', label: 'Termination' },
    { id: 'disclaimers', label: 'Disclaimers' },
    { id: 'liability', label: 'Limitation of liability' },
    { id: 'changes', label: 'Changes to these terms' },
    { id: 'governing-law', label: 'Governing law' },
    { id: 'contact', label: 'Contact' },
];
</script>

<template>
    <Head title="Terms of Service" />

    <LegalLayout
        title="Terms of Service"
        last-updated="July 20, 2026"
        :sections="sections"
    >
        <section id="overview">
            <h2>Overview</h2>
            <p>
                These terms govern your use of PostDoffo, the API workspace
                available at this website. By creating an account or using
                PostDoffo, you agree to these terms. If you're using PostDoffo
                on behalf of an organization, you're confirming that you have
                the authority to bind that organization to these terms.
            </p>
        </section>

        <section id="accounts">
            <h2>Your account</h2>
            <p>
                You need an account to use PostDoffo. You're responsible for
                keeping your credentials, passkeys and two-factor recovery codes
                secure, and for all activity that happens under your account.
                Tell us right away if you notice unauthorized access. You must
                be at least 16 years old to create an account.
            </p>
        </section>

        <section id="acceptable-use">
            <h2>Acceptable use</h2>
            <p>When using PostDoffo, you agree not to:</p>
            <ul>
                <li>
                    Use the service to send requests intended to disrupt,
                    overload or gain unauthorized access to systems you don't
                    own or have permission to test
                </li>
                <li>
                    Upload malware or attempt to probe, scan or breach
                    PostDoffo's own infrastructure
                </li>
                <li>
                    Reverse-engineer, resell or white-label the service without
                    our written permission
                </li>
                <li>
                    Use the service to violate any applicable law or the rights
                    of a third party
                </li>
                <li>
                    Attempt to circumvent rate limits, quotas or authentication
                    controls
                </li>
            </ul>
            <p>
                We may suspend or terminate accounts that violate these rules.
            </p>
        </section>

        <section id="your-content">
            <h2>Your content</h2>
            <p>
                You own the collections, requests, environments and other
                content you create in PostDoffo ("your content"). We don't claim
                ownership over it. You grant us a limited license to store,
                process and transmit your content solely to operate and improve
                the service, such as syncing it across your devices or executing
                requests you initiate. You're responsible for making sure you
                have the right to store any third-party API credentials or data
                you put into PostDoffo.
            </p>
        </section>

        <section id="workspaces-teams">
            <h2>Workspaces & teams</h2>
            <p>
                Workspaces can be shared with teammates you invite. Anyone you
                add to a workspace can see and, depending on their role, edit
                the collections, environments and history inside it. You're
                responsible for managing who has access to your workspaces and
                for removing members who should no longer have it.
            </p>
        </section>

        <section id="availability">
            <h2>Availability</h2>
            <p>
                We aim to keep PostDoffo available and reliable, but we don't
                guarantee uninterrupted access. We may need to pause the service
                for maintenance, or modify or discontinue features, with
                reasonable notice where practical.
            </p>
        </section>

        <section id="termination">
            <h2>Termination</h2>
            <p>
                You can delete your account at any time from your account
                settings. We may suspend or terminate your access if you violate
                these terms, create risk or legal exposure for us, or if we
                discontinue the service. Where possible, we'll give you notice
                and a chance to export your content first.
            </p>
        </section>

        <section id="disclaimers">
            <h2>Disclaimers</h2>
            <p>
                PostDoffo is provided "as is" and "as available," without
                warranties of any kind, whether express or implied, including
                warranties of merchantability, fitness for a particular purpose,
                or non-infringement. We don't warrant that the service will be
                error-free or uninterrupted, or that responses returned by
                third-party APIs you call through PostDoffo will be accurate or
                safe.
            </p>
        </section>

        <section id="liability">
            <h2>Limitation of liability</h2>
            <p>
                To the maximum extent permitted by law, PostDoffo and its
                creators won't be liable for indirect, incidental, special,
                consequential or punitive damages, or for lost profits or data,
                arising from your use of the service. Our total liability for
                any claim relating to the service is limited to the amount you
                paid us, if any, in the 12 months before the claim arose.
            </p>
        </section>

        <section id="changes">
            <h2>Changes to these terms</h2>
            <p>
                We may update these terms from time to time. If we make a
                material change, we'll notify you by email or with a notice
                inside the app before it takes effect. Continuing to use
                PostDoffo after a change takes effect means you accept the
                updated terms.
            </p>
        </section>

        <section id="governing-law">
            <h2>Governing law</h2>
            <p>
                These terms are governed by the laws of the jurisdiction in
                which PostDoffo is operated, without regard to its conflict of
                laws principles, unless a different jurisdiction is required by
                local law.
            </p>
        </section>

        <section id="contact">
            <h2>Contact</h2>
            <p>
                Questions about these terms can be sent to
                <a
                    href="mailto:legal@postdoffo.com"
                    class="text-foreground underline underline-offset-4"
                    >legal@postdoffo.com</a
                >.
            </p>
        </section>
    </LegalLayout>
</template>
