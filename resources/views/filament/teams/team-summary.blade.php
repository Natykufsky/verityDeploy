<div class="space-y-4">
    <div class="rounded-3xl border border-amber-400/25 bg-gradient-to-br from-slate-950 via-slate-950 to-amber-500/10 p-5 shadow-[0_24px_80px_-32px_rgba(0,0,0,.85)]">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-amber-200">
                    Team workspace
                </div>
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-white">{{ $getRecord()->name }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                        {{ $getRecord()->description ?: 'This team keeps servers, sites, and access together in one shared workspace.' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2 text-left text-xs text-slate-400 sm:grid-cols-2 lg:grid-cols-1 lg:text-right">
                <div class="rounded-2xl border border-slate-200/10 bg-slate-950/80 px-3 py-2">
                    <div class="uppercase tracking-[0.2em] text-slate-500">Slug</div>
                    <div class="mt-1 font-mono text-sm text-white">{{ $getRecord()->slug }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200/10 bg-slate-950/80 px-3 py-2">
                    <div class="uppercase tracking-[0.2em] text-slate-500">Owner</div>
                    <div class="mt-1 text-sm font-medium text-white">{{ $getRecord()->owner?->name ?? 'Unassigned' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Members</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ $getRecord()->members()->count() }}</div>
            <div class="mt-1 text-xs text-slate-400">People who can work in this team.</div>
        </div>
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Pending invites</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ $getRecord()->pendingInvitations()->count() }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $getRecord()->pendingInvitationExpiryLabel() }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Servers</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ $getRecord()->servers()->count() }}</div>
            <div class="mt-1 text-xs text-slate-400">Infrastructure owned or shared by this team.</div>
        </div>
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Sites</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ $getRecord()->sites()->count() }}</div>
            <div class="mt-1 text-xs text-slate-400">Deployable apps attached to the team.</div>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4 dark:border-white/10">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Quick actions</div>
            <div class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2">
                    Invite teammates from the header action.
                </div>
                <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2">
                    Use role filters to separate admins, members, and viewers.
                </div>
                <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2">
                    Resend or revoke pending invites from the invitations table.
                </div>
                <div class="rounded-xl border border-slate-200/10 bg-slate-900/80 px-3 py-2">
                    Update member roles directly without leaving the page.
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-400/25 bg-amber-500/10 p-4 text-amber-50">
            <div class="text-xs uppercase tracking-[0.2em] text-amber-200">Invite lifecycle</div>
            <div class="mt-3 text-sm leading-6 text-amber-50/90">
                A teammate invite is valid for a short window. If they have not accepted yet, use <span class="font-semibold">Resend</span>
                to generate a fresh link, or <span class="font-semibold">Cancel invite</span> to revoke access before it is used.
            </div>
            <div class="mt-3 text-xs leading-6 text-amber-100/80">
                The pending invite badge and expiry label make it easy to spot teams that need follow-up.
            </div>
        </div>
    </div>
</div>
