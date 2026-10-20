export type User = {
    id: number;
    name: string;
    email: string;
};

export type CurrentChurch = {
    name: string;
    slug: string;
    countryCode: string | null;
    currencyCode: string | null;
    timezone: string | null;
    role: string | null;
};

export type Flash = {
    success?: string | null;
    error?: string | null;
};

export type PlatformAdmin = {
    id: number;
    name: string;
    email: string;
};

export type PageProps = {
    appName: string;
    auth: {
        user: User | null;
    };
    platformAdmin: PlatformAdmin | null;
    currentChurch: CurrentChurch | null;
    flash: Flash;
    errors: Record<string, string>;
};

export type ChurchSummary = {
    name: string;
    slug: string;
    countryCode: string | null;
    currencyCode: string | null;
    timezone: string | null;
};

export type MembershipSummary = {
    id: number;
    role: string;
    church: ChurchSummary;
};

export type CountryOption = {
    code: string;
    name: string;
    timezone: string;
    currencyCode: string;
};

export type CurrencyOption = {
    code: string;
    name: string;
};

export type ChurchCurrencySummary = {
    code: string;
    isDefault: boolean;
};

export type LocaleOption = {
    code: string;
    name: string;
};

export type ChurchStatusValue = 'active' | 'suspended' | 'inactive' | 'archived';

export type StatusOption = {
    value: ChurchStatusValue;
    label: string;
};

export type ChurchMetrics = {
    activeMemberships: number;
    people: number;
    events: number;
    servicePlans: number;
    workflows: number;
};

export type AdminChurchSummary = {
    id: number;
    name: string;
    slug: string;
    status: ChurchStatusValue;
    statusLabel: string;
    countryCode: string | null;
    currencyCode: string | null;
    timezone: string | null;
    createdAt: string | null;
    metrics: ChurchMetrics;
};

export type AdminChurchDetail = AdminChurchSummary & {
    locale: string;
    statusReason: string | null;
    statusChangedAt: string | null;
    statusChangedBy: string | null;
    archivedAt: string | null;
    updatedAt: string | null;
};

export type AdminAuditEntry = {
    id: number;
    action: string;
    actor: string | null;
    actorEmail: string | null;
    createdAt: string | null;
    metadata: Record<string, unknown>;
};

export type AdminPagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
};

export type PlanSummary = {
    key: string;
    name: string;
    description: string | null;
    priceMinor: number;
    currencyCode: string;
    billingInterval: string;
};

export type SubscriptionSummary = {
    status: string;
    statusLabel: string;
    startedAt: string | null;
    planChangedAt: string | null;
    endsAt: string | null;
    notes: string | null;
};

export type PlanLimitSource = 'plan' | 'override';

export type PlanUsageItem = {
    key: string;
    label: string;
    description: string;
    unit: string;
    used: number;
    limit: number | null;
    remaining: number | null;
    unlimited: boolean;
    exceeded: boolean;
    source: PlanLimitSource;
};

export type PlanFeatureItem = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    source: PlanLimitSource;
};

export type PlanOverview = {
    plan: PlanSummary | null;
    subscription: SubscriptionSummary | null;
    usage: Record<string, PlanUsageItem>;
    features: PlanFeatureItem[];
};

export type AdminPlanOption = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    priceMinor: number;
    currencyCode: string;
    billingInterval: string;
    limits: Record<string, number | null>;
    features: string[];
};

export type AdminPlanResource = PlanUsageItem & {
    planLimit: number | null;
    override: {
        exists: boolean;
        mode: 'inherit' | 'unlimited' | 'custom';
        limitValue: number | null;
        note: string | null;
    };
};

export type AdminPlanFeature = PlanFeatureItem & {
    override: {
        exists: boolean;
        mode: 'inherit' | 'enabled' | 'disabled';
        isEnabled: boolean | null;
        note: string | null;
    };
};

export type AdminSubscriptionDetail = {
    planId: number | null;
    planKey: string | null;
    planName: string | null;
    status: string | null;
    statusLabel: string | null;
    startedAt: string | null;
    planChangedAt: string | null;
    endsAt: string | null;
    notes: string | null;
    assignedBy: string | null;
};
