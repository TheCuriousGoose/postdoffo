<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/layouts/legal/LegalLayout.vue';

const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'information-we-collect', label: 'Information we collect' },
    { id: 'how-we-use-it', label: 'How we use it' },
    { id: 'third-party-sign-in', label: 'Third-party sign-in' },
    { id: 'cookies', label: 'Cookies & sessions' },
    { id: 'sharing', label: 'Sharing your data' },
    { id: 'retention', label: 'Data retention' },
    { id: 'security', label: 'Security' },
    { id: 'your-rights', label: 'Your rights' },
    { id: 'children', label: "Children's privacy" },
    { id: 'changes', label: 'Changes to this policy' },
    { id: 'contact', label: 'Contact' },
];
</script>

<template>
    <Head title="Privacy Policy" />

    <LegalLayout
        title="Privacy Policy"
        last-updated="July 20, 2026"
        :sections="sections"
    >
        <section id="overview">
            <h2>Overview</h2>
            <p>
                PostDoffo is an API workspace: you use it to build, organize and
                send HTTP requests, and to store the collections, environments
                and history that go along with them. This policy explains what
                information we collect when you use PostDoffo, why we collect
                it, and the choices you have.
            </p>
        </section>

        <section id="information-we-collect">
            <h2>Information we collect</h2>
            <p>We collect a few different kinds of information:</p>
            <ul>
                <li>
                    <strong>Account information.</strong> Your name, email
                    address, and password (stored as a salted hash, never in
                    plain text). If you sign in with Google or GitHub, we
                    receive your name, email address and profile identifier from
                    that provider.
                </li>
                <li>
                    <strong>Workspace content.</strong> The collections,
                    folders, requests, environments and environment variables
                    you create, including any values you choose to store in
                    them, such as API keys or tokens. This content is yours, and
                    we treat it as confidential.
                </li>
                <li>
                    <strong>Request history.</strong> A record of the requests
                    you send through PostDoffo and the responses returned, so
                    you can look back at previous calls.
                </li>
                <li>
                    <strong>Team information.</strong> If you invite teammates
                    to a workspace, we store their email address, role, and
                    invitation status.
                </li>
                <li>
                    <strong>Device and usage data.</strong> Basic technical
                    information such as your IP address, browser type, and the
                    pages or actions you use, collected automatically to keep
                    the service reliable and secure.
                </li>
            </ul>
        </section>

        <section id="how-we-use-it">
            <h2>How we use it</h2>
            <p>We use the information above to:</p>
            <ul>
                <li>
                    Provide, maintain and secure your account and workspaces
                </li>
                <li>
                    Sync your collections, environments and history across
                    sessions
                </li>
                <li>
                    Send account-related emails, such as email verification and
                    security alerts
                </li>
                <li>Respond to support requests</li>
                <li>
                    Detect, investigate and prevent abuse or unauthorized access
                </li>
                <li>Improve the reliability and performance of PostDoffo</li>
            </ul>
            <p>
                We do not sell your data, and we do not use the contents of your
                requests, collections or environment variables for advertising.
            </p>
        </section>

        <section id="third-party-sign-in">
            <h2>Third-party sign-in</h2>
            <p>
                If you choose to sign in with Google or GitHub, those providers
                share your name, email address and account identifier with us so
                we can create or match your PostDoffo account. We don't receive
                your password for those accounts, and you can review or revoke
                PostDoffo's access at any time from your Google or GitHub
                account settings.
            </p>
            <p>
                If you enable a passkey or two-factor authentication, we store
                the cryptographic credential or the encrypted secret needed to
                verify you on future sign-ins. We never receive or store your
                biometric data itself; that stays on your device.
            </p>
        </section>

        <section id="cookies">
            <h2>Cookies & sessions</h2>
            <p>
                PostDoffo uses a single session cookie to keep you signed in and
                a CSRF cookie to protect form submissions. Both are strictly
                necessary for the service to function and are not used for
                advertising or cross-site tracking. We don't run third-party
                analytics or ad trackers on PostDoffo.
            </p>
        </section>

        <section id="sharing">
            <h2>Sharing your data</h2>
            <p>We share information only in the following circumstances:</p>
            <ul>
                <li>
                    With infrastructure providers who host our database,
                    application servers and outgoing email on our behalf, under
                    contracts that limit their use of your data to providing
                    that service
                </li>
                <li>
                    With other members of a workspace you've joined or invited,
                    limited to what that workspace's collections and permissions
                    expose to them
                </li>
                <li>
                    If required to comply with a legal obligation, court order
                    or valid government request
                </li>
                <li>
                    If necessary to investigate or prevent fraud, security
                    incidents or abuse of the service
                </li>
            </ul>
        </section>

        <section id="retention">
            <h2>Data retention</h2>
            <p>
                We keep your account and workspace data for as long as your
                account is active. If you delete a workspace, collection or
                request, it's removed from our production database; residual
                copies in backups are purged on a rolling schedule. If you
                delete your account, we remove your personal information within
                30 days, except where we're required to retain records for legal
                or security purposes.
            </p>
        </section>

        <section id="security">
            <h2>Security</h2>
            <p>
                We apply reasonable technical and organizational safeguards to
                protect your data, including hashed passwords, encrypted
                connections (HTTPS/TLS) between your browser and our servers,
                and access controls on our infrastructure. No method of
                transmission or storage is completely secure, so we can't
                guarantee absolute security. Because environment variables and
                request bodies can contain sensitive values you supply yourself,
                avoid storing production credentials you wouldn't want exposed
                in the event of a breach.
            </p>
        </section>

        <section id="your-rights">
            <h2>Your rights</h2>
            <p>Depending on where you live, you may have the right to:</p>
            <ul>
                <li>Access the personal data we hold about you</li>
                <li>
                    Correct inaccurate data, from your account settings or by
                    contacting us
                </li>
                <li>
                    Export your workspace data or request a copy of your account
                    data
                </li>
                <li>Delete your account and associated personal data</li>
                <li>Object to or restrict certain processing of your data</li>
            </ul>
            <p>
                To exercise any of these rights, contact us using the details
                below. We'll respond within a reasonable timeframe.
            </p>
        </section>

        <section id="children">
            <h2>Children's privacy</h2>
            <p>
                PostDoffo is not directed at children under 16, and we don't
                knowingly collect personal information from them. If you believe
                a child has provided us with personal data, contact us and we'll
                remove it.
            </p>
        </section>

        <section id="changes">
            <h2>Changes to this policy</h2>
            <p>
                We may update this policy as PostDoffo evolves. If we make a
                material change, we'll notify you by email or with a notice
                inside the app before it takes effect. The date at the top of
                this page always reflects the latest revision.
            </p>
        </section>

        <section id="contact">
            <h2>Contact</h2>
            <p>
                Questions about this policy or your data can be sent to
                <a
                    href="mailto:privacy@postdoffo.com"
                    class="text-foreground underline underline-offset-4"
                    >privacy@postdoffo.com</a
                >.
            </p>
        </section>
    </LegalLayout>
</template>
