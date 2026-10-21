export type User = {
    id: number;
    name: string;
    email: string;
};

export type CurrentPlanSummary = {
    key: string;
    name: string;
    priceMinor: number;
    currencyCode: string;
    billingInterval: string;
    isFree: boolean;
};

export type CurrentChurch = {
    name: string;
    slug: string;
    countryCode: string | null;
    currencyCode: string | null;
    timezone: string | null;
    role: string | null;
    roleLabel: string;
    permissions: string[];
    plan: CurrentPlanSummary | null;
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
    canViewPlan: boolean;
    plan: CurrentPlanSummary | null;
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

export type RoleOption = {
    value: string;
    label: string;
};

export type Pagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
};

export type PersonSummary = {
    id: number;
    firstName: string;
    lastName: string | null;
    fullName: string;
    email: string | null;
    phone: string | null;
    status: string;
    createdAt?: string | null;
    updatedAt?: string | null;
};

export type CustomFieldTypeValue =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'boolean'
    | 'select'
    | 'multiselect';

export type CustomFieldOption = {
    value: string;
    label: string;
};

export type CustomFieldDefinitionSummary = {
    id: number;
    key: string;
    label: string;
    type: CustomFieldTypeValue;
    typeLabel: string;
    options: CustomFieldOption[];
    isRequired: boolean;
    isActive: boolean;
};

export type PersonCustomFieldValue = {
    key: string;
    label: string;
    type: CustomFieldTypeValue;
    value: string;
};

export type CustomFieldInputValue = string | number | boolean | string[] | null;

export type ChurchMemberSummary = {
    id: number;
    userId: number;
    name: string | null;
    email: string | null;
    role: string;
    roleLabel: string;
    status: string;
    joinedAt: string | null;
    isCurrentUser: boolean;
};

export type ChurchInvitationSummary = {
    id: number;
    email: string;
    role: string;
    roleLabel: string;
    status: string;
    expiresAt: string | null;
    expired: boolean;
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
    isFree: boolean;
};

export type AvailablePlan = CurrentPlanSummary & {
    description: string;
    isCurrent: boolean;
    limits: Record<string, number | null>;
    features: string[];
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

export type InvoiceStatusValue = 'issued' | 'paid' | 'void' | 'overdue';

export type PaymentMethodValue = 'cash' | 'transfer' | 'card' | 'mobile_payment' | 'other';

export type InvoiceStatusOption = {
    value: InvoiceStatusValue;
    label: string;
};

export type PaymentMethodOption = {
    value: PaymentMethodValue;
    label: string;
};

export type InvoicePayment = {
    id: number;
    amountMinor: number;
    currencyCode: string;
    method: PaymentMethodValue;
    methodLabel: string;
    reference: string | null;
    notes: string | null;
    paidAt: string | null;
    recordedBy: string | null;
};

export type ChurchInvoice = {
    id: number;
    number: string;
    status: InvoiceStatusValue;
    statusLabel: string;
    currencyCode: string;
    amountMinor: number;
    paidMinor: number;
    remainingMinor: number;
    description: string | null;
    notes: string | null;
    issuedAt: string | null;
    dueAt: string | null;
    paidAt: string | null;
    voidedAt: string | null;
    voidReason: string | null;
    payments: InvoicePayment[];
};

export type AdminInvoice = ChurchInvoice & {
    church: {
        id: number | null;
        name: string;
        slug: string;
        exists: boolean;
    };
    issuedBy: string | null;
};

export type ChurchBilling = {
    canView: true;
    invoices: ChurchInvoice[];
} | null;

export type SegmentTypeValue = 'dynamic' | 'static';

export type SegmentFilterOption = {
    id: number;
    name: string;
    type: SegmentTypeValue;
    typeLabel: string;
    isActive: boolean;
};

export type SegmentTypeOption = {
    value: SegmentTypeValue;
    label: string;
    description: string;
};

export type SegmentFieldTypeValue =
    | 'text'
    | 'number'
    | 'date'
    | 'boolean'
    | 'select'
    | 'multiselect';

export type SegmentOperatorOption = {
    value: string;
    label: string;
    requiresValue: boolean;
};

export type SegmentField = {
    source: 'person' | 'custom';
    key: string;
    label: string;
    type: SegmentFieldTypeValue;
    options: CustomFieldOption[];
    operators: SegmentOperatorOption[];
};

export type SegmentConditionValue = string | number | string[] | null;

export type SegmentCondition = {
    source: 'person' | 'custom';
    field: string;
    operator: string;
    value: SegmentConditionValue;
};

export type SegmentRuleSet = {
    version: number;
    match: 'all' | 'any';
    conditions: SegmentCondition[];
};

export type SegmentSummary = {
    id: number;
    name: string;
    description: string | null;
    type: SegmentTypeValue;
    typeLabel: string;
    isActive: boolean;
    memberCount: number;
    rulesSummary: string | null;
    createdAt: string | null;
};

export type SegmentDetail = SegmentSummary & {
    rules: SegmentRuleSet | null;
    updatedAt: string | null;
};
