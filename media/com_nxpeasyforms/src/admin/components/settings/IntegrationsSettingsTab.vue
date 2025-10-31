<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-integrations"
        id="nxp-panel-integrations"
    >
                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.zapier" alt="" /></span>
                                        <div>
                                            <h3>{{ __("Zapier", "nxp-easy-forms") }}</h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Connect your forms to 5,000+ apps using Zapier's Webhooks by Zapier trigger.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable Zapier", "nxp-easy-forms")
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.zapier.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.zapier.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Zapier webhook URL",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="url"
                                                v-model="
                                                    local.integrations.zapier.webhook_url
                                                "
                                                placeholder="https://hooks.zapier.com/hooks/catch/..."
                                            />
                                            <small class="nxp-setting__hint">
                                                {{
                                                    __(
                                                        "In Zapier, create a new Zap → choose 'Webhooks by Zapier' → select 'Catch Hook' → copy the URL and paste it here.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.make" alt="" /></span>
                                        <div>
                                            <h3>{{ __("Make", "nxp-easy-forms") }}</h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Automate workflows with Make.com (formerly Integromat) using webhooks.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable Make", "nxp-easy-forms")
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.make.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.make.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Make webhook URL",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="url"
                                                v-model="
                                                    local.integrations.make.webhook_url
                                                "
                                                placeholder="https://hook.make.com/..."
                                            />
                                            <small class="nxp-setting__hint">
                                                {{
                                                    __(
                                                        "In Make.com, create a new Scenario → add 'Webhooks' module → choose 'Custom webhook' → copy the URL and paste it here.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.slack" alt="" /></span>
                                        <div>
                                            <h3>{{ __("Slack", "nxp-easy-forms") }}</h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Send channel notifications using Slack Incoming Webhooks (legacy custom integration).",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable Slack", "nxp-easy-forms")
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.slack.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.slack.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Slack webhook URL", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="url"
                                                v-model="
                                                    local.integrations.slack.webhook_url
                                                "
                                                placeholder="https://hooks.slack.com/services/..."
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Message template (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <textarea
                                                rows="4"
                                                v-model="
                                                    local.integrations.slack
                                                        .message_template
                                                "
                                                :placeholder="slackTemplatePlaceholder"
                                            ></textarea>
                                            <small class="nxp-setting__hint">
                                                {{ slackTemplateHint }}
                                            </small>
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.teams" alt="" /></span>
                                        <div>
                                            <h3>
                                                {{
                                                    __(
                                                        "Microsoft Teams",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Post adaptive cards to Microsoft Teams channels via Incoming Webhooks.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __(
                                                "Enable Microsoft Teams",
                                                "nxp-easy-forms",
                                            )
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.teams.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.teams.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Teams webhook URL", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="url"
                                                v-model="
                                                    local.integrations.teams.webhook_url
                                                "
                                                placeholder="https://<tenant>.webhook.office.com/webhookb2/..."
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Card title", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="
                                                    local.integrations.teams.card_title
                                                "
                                                placeholder="New form submission"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Message body (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <textarea
                                                rows="4"
                                                v-model="
                                                    local.integrations.teams
                                                        .message_template
                                                "
                                                :placeholder="teamsTemplatePlaceholder"
                                            ></textarea>
                                            <small class="nxp-setting__hint">
                                                {{ teamsTemplateHint }}
                                            </small>
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.mailchimp" alt="" /></span>
                                        <div>
                                            <h3>{{ __("Mailchimp", "nxp-easy-forms") }}</h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Subscribe contacts to a Mailchimp audience and optionally enable double opt-in.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable Mailchimp", "nxp-easy-forms")
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.mailchimp.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.mailchimp.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("API key", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="password"
                                                v-model="local.integrations.mailchimp.api_key"
                                                autocomplete="off"
                                                placeholder="abcd1234-us1"
                                            />
                                            <small
                                                class="nxp-setting__hint"
                                                v-if="
                                                    local.integrations.mailchimp
                                                        .api_key_set &&
                                                    !local.integrations.mailchimp
                                                        .remove_api_key
                                                "
                                            >
                                                {{
                                                    __(
                                                        "A key is already stored. Leave this field blank to keep it.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                        <label
                                            class="nxp-setting nxp-setting--switch"
                                            v-if="local.integrations.mailchimp.api_key_set"
                                        >
                                            <span>{{
                                                __(
                                                    "Remove stored API key",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="checkbox"
                                                v-model="
                                                    local.integrations.mailchimp
                                                        .remove_api_key
                                                "
                                            />
                                        </label>
                                        <div class="nxp-setting">
                                            <button
                                                type="button"
                                                class="button button-secondary nxp-integration__action"
                                                @click="fetchMailchimpAudiences"
                                                :disabled="mailchimpAudiencesLoading"
                                            >
                                                <span
                                                    class="fa-solid fa-arrows-rotate"
                                                    :class="{ 'fa-spin': mailchimpAudiencesLoading }"
                                                    aria-hidden="true"
                                                ></span>
                                                <span v-if="mailchimpAudiencesLoading">
                                                    {{ __("Fetching…", "nxp-easy-forms") }}
                                                </span>
                                                <span v-else>
                                                    {{
                                                        __(
                                                            "Fetch audiences",
                                                            "nxp-easy-forms",
                                                        )
                                                    }}
                                                </span>
                                            </button>
                                            <small class="nxp-integration-hint">
                                                {{
                                                    __(
                                                        "You can fetch available audiences once an API key is provided and saved.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                            <p
                                                class="nxp-integration-inline-error"
                                                v-if="mailchimpAudiencesError"
                                            >
                                                {{ mailchimpAudiencesError }}
                                            </p>
                                        </div>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Audience (list)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <select
                                                v-model="local.integrations.mailchimp.list_id"
                                            >
                                                <option value="">
                                                    {{
                                                        __(
                                                            "Select audience…",
                                                            "nxp-easy-forms",
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="audience in mailchimpAudiences"
                                                    :key="audience.id"
                                                    :value="audience.id"
                                                >
                                                    {{ audience.name }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Email field", "nxp-easy-forms")
                                            }}</span>
                                            <select
                                                v-model="
                                                    local.integrations.mailchimp
                                                        .email_field
                                                "
                                            >
                                                <option value="">
                                                    {{
                                                        __(
                                                            "Select field…",
                                                            "nxp-easy-forms",
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in emailFieldOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "First name field (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <select
                                                v-model="
                                                    local.integrations.mailchimp
                                                        .first_name_field
                                                "
                                            >
                                                <option value="">
                                                    {{
                                                        __("None", "nxp-easy-forms")
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in mappableFieldOptions"
                                                    :key="`mc-first-${option.value}`"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Last name field (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <select
                                                v-model="
                                                    local.integrations.mailchimp
                                                        .last_name_field
                                                "
                                            >
                                                <option value="">
                                                    {{
                                                        __("None", "nxp-easy-forms")
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in mappableFieldOptions"
                                                    :key="`mc-last-${option.value}`"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Tags (comma separated)", "nxp-easy-forms")
                                            }}</span>
                                            <textarea
                                                rows="3"
                                                v-model="
                                                    local.integrations.mailchimp.tags_input
                                                "
                                                placeholder="vip, customer, newsletter"
                                            ></textarea>
                                            <small class="nxp-setting__hint">
                                                {{
                                                    __(
                                                        "Each tag is activated for the subscriber. Leave blank to skip tagging.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                        <label class="nxp-setting nxp-setting--switch">
                                            <span>{{
                                                __("Enable double opt-in", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="checkbox"
                                                v-model="
                                                    local.integrations.mailchimp
                                                        .double_opt_in
                                                "
                                            />
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.salesforce" alt="" /></span>
                                        <div>
                                            <h3>
                                                {{
                                                    __(
                                                        "Salesforce (Essentials)",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Create Salesforce leads using the classic Web-to-Lead endpoint—no OAuth required.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __(
                                                "Enable Salesforce",
                                                "nxp-easy-forms",
                                            )
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.salesforce.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.salesforce.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Organisation ID", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="local.integrations.salesforce.org_id"
                                                placeholder="00DXXXXXXXXXXXX"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Lead source (optional)", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="
                                                    local.integrations.salesforce.lead_source
                                                "
                                                placeholder="Web"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Assignment rule ID (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="
                                                    local.integrations.salesforce
                                                        .assignment_rule_id
                                                "
                                                placeholder="01QXXXXXXXXXXXX"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Debug email (optional)",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="email"
                                                v-model="
                                                    local.integrations.salesforce.debug_email
                                                "
                                                placeholder="admin@example.com"
                                            />
                                        </label>
                                        <div class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Field mappings",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <div class="nxp-integration-mappings">
                                                <div
                                                    class="nxp-integration-mapping__row"
                                                    v-for="mapping in local.integrations.salesforce.mappings"
                                                    :key="mapping.id"
                                                >
                                                    <input
                                                        type="text"
                                                        v-model="mapping.salesforce_field"
                                                        placeholder="email, first_name, company"
                                                    />
                                                    <select v-model="mapping.form_field">
                                                        <option value="">
                                                            {{
                                                                __(
                                                                    "Select field…",
                                                                    "nxp-easy-forms",
                                                                )
                                                            }}
                                                        </option>
                                                        <option
                                                            v-for="option in mappableFieldOptions"
                                                            :key="`sf-field-${option.value}`"
                                                            :value="option.value"
                                                        >
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                    <button
                                                        type="button"
                                                        class="button button-link-delete"
                                                        @click="removeSalesforceMapping(mapping.id)"
                                                    >
                                                        <img :src="ICON_REMOVE" alt="" aria-hidden="true" />
                                                        <span class="screen-reader-text">{{ __("Remove", "nxp-easy-forms") }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="nxp-integration-hint">
                                                {{
                                                    __(
                                                        "Salesforce expects Web-to-Lead field names (for example: first_name, last_name, email, company, description).",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                            <button
                                                type="button"
                                                class="button button-secondary nxp-integration__action"
                                                @click="addSalesforceMapping"
                                            >
                                                <span class="fa-solid fa-plus" aria-hidden="true"></span>
                                                <span>{{ __("Add mapping", "nxp-easy-forms") }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.hubspot" alt="" /></span>
                                        <div>
                                            <h3>{{ __("HubSpot", "nxp-easy-forms") }}</h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Submit contacts to a HubSpot form using a Private App access token.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable HubSpot", "nxp-easy-forms")
                                        }}</span>
                                        <input
                                            type="checkbox"
                                            v-model="local.integrations.hubspot.enabled"
                                        />
                                    </label>
                                    <div
                                        v-if="local.integrations.hubspot.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Private app access token",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="password"
                                                v-model="local.integrations.hubspot.access_token"
                                                autocomplete="off"
                                            />
                                            <small
                                                class="nxp-setting__hint"
                                                v-if="
                                                    local.integrations.hubspot
                                                        .access_token_set &&
                                                    !local.integrations.hubspot
                                                        .remove_access_token
                                                "
                                            >
                                                {{
                                                    __(
                                                        "A token is already stored. Leave blank to continue using it.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                        <label
                                            class="nxp-setting nxp-setting--switch"
                                            v-if="local.integrations.hubspot.access_token_set"
                                        >
                                            <span>{{
                                                __(
                                                    "Remove stored token",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <input
                                                type="checkbox"
                                                v-model="
                                                    local.integrations.hubspot
                                                        .remove_access_token
                                                "
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Portal ID", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="local.integrations.hubspot.portal_id"
                                                placeholder="1234567"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Form GUID", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="text"
                                                v-model="local.integrations.hubspot.form_guid"
                                                placeholder="abcd1234-5678-90ab-cdef-1234567890ab"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Email field", "nxp-easy-forms")
                                            }}</span>
                                            <select
                                                v-model="
                                                    local.integrations.hubspot.email_field
                                                "
                                            >
                                                <option value="">
                                                    {{
                                                        __(
                                                            "Select field…",
                                                            "nxp-easy-forms",
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in emailFieldOptions"
                                                    :key="`hb-email-${option.value}`"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <div class="nxp-setting">
                                            <span>{{
                                                __(
                                                    "Property mappings",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <div class="nxp-integration-mappings">
                                                <div
                                                    class="nxp-integration-mapping__row"
                                                    v-for="mapping in local.integrations.hubspot.field_mappings"
                                                    :key="mapping.id"
                                                >
                                                    <input
                                                        type="text"
                                                        v-model="mapping.hubspot_field"
                                                        placeholder="firstname"
                                                    />
                                                    <select v-model="mapping.form_field">
                                                        <option value="">
                                                            {{
                                                                __(
                                                                    "Select field…",
                                                                    "nxp-easy-forms",
                                                                )
                                                            }}
                                                        </option>
                                                        <option
                                                            v-for="option in mappableFieldOptions"
                                                            :key="`hb-field-${option.value}`"
                                                            :value="option.value"
                                                        >
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                    <button
                                                        type="button"
                                                        class="button button-link-delete"
                                                        @click="removeHubspotMapping(mapping.id)"
                                                    >
                                                        <img :src="ICON_REMOVE" alt="" aria-hidden="true" />
                                                        <span class="screen-reader-text">{{ __("Remove", "nxp-easy-forms") }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="nxp-integration-hint">
                                                {{
                                                    __(
                                                        "Map HubSpot property names (for example firstname, lastname, company) to your form fields.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                            <button
                                                type="button"
                                                class="button button-secondary nxp-integration__action"
                                                @click="addHubspotMapping"
                                            >
                                                <span class="fa-solid fa-plus" aria-hidden="true"></span>
                                                <span>{{ __("Add mapping", "nxp-easy-forms") }}</span>
                                            </button>
                                        </div>
                                        <label class="nxp-setting nxp-setting--switch">
                                            <span>{{
                                                __("GDPR consent enabled", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="checkbox"
                                                v-model="
                                                    local.integrations.hubspot
                                                        .legal_consent
                                                "
                                            />
                                        </label>
                                        <label
                                            class="nxp-setting"
                                            v-if="local.integrations.hubspot.legal_consent"
                                        >
                                            <span>{{
                                                __(
                                                    "Consent text",
                                                    "nxp-easy-forms",
                                                )
                                            }}</span>
                                            <textarea
                                                rows="3"
                                                v-model="
                                                    local.integrations.hubspot
                                                        .consent_text
                                                "
                                                placeholder="I agree to receive marketing communications."
                                            ></textarea>
                                        </label>
                                    </div>
                                </div>

                                <div class="nxp-integration-card">
                                    <div class="nxp-integration-header">
                                        <span class="nxp-integration-icon"><img :src="integrationIcons.webhook" alt="" /></span>
                                        <div>
                                            <h3>
                                                {{
                                                    __(
                                                        "General webhook submissions",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </h3>
                                            <p class="nxp-integration-description">
                                                {{
                                                    __(
                                                        "Post submission payloads to your own endpoint using the built-in webhook sender.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="nxp-setting nxp-setting--switch">
                                        <span>{{
                                            __("Enable general webhook", "nxp-easy-forms")
                                        }}</span>
                                        <input type="checkbox" v-model="local.webhooks.enabled" />
                                    </label>
                                    <div
                                        v-if="local.webhooks.enabled"
                                        class="nxp-setting-group"
                                    >
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Webhook endpoint URL", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="url"
                                                v-model="local.webhooks.endpoint"
                                                placeholder="https://api.example.com/webhooks"
                                            />
                                        </label>
                                        <label class="nxp-setting">
                                            <span>{{
                                                __("Signing secret (optional)", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="password"
                                                v-model="local.webhooks.secret"
                                                :placeholder="
                                                    local.webhooks.secret_set ? '••••••' : ''
                                                "
                                                autocomplete="new-password"
                                            />
                                            <small class="nxp-setting__hint" v-if="local.webhooks.secret_set">
                                                {{
                                                    __(
                                                        "Secrets are write-only. Leave blank to keep the existing secret or toggle removal below.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                            <small class="nxp-setting__hint" v-else>
                                                {{
                                                    __(
                                                        "Secrets are write-only and encrypted when stored.",
                                                        "nxp-easy-forms",
                                                    )
                                                }}
                                            </small>
                                        </label>
                                        <label
                                            class="nxp-setting nxp-setting--switch"
                                            v-if="local.webhooks.secret_set"
                                        >
                                            <span>{{
                                                __("Remove stored secret", "nxp-easy-forms")
                                            }}</span>
                                            <input
                                                type="checkbox"
                                                v-model="local.webhooks.remove_secret"
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>
</template>

<script setup>
import { inject, computed } from "vue";
import { __ } from "@/utils/translate";
import ICON_REMOVE from "../../../../assets/icons/square-letter-x.svg";
import ICON_ZAPIER from "../../../../assets/icons/bolt.svg";
import ICON_MAKE from "../../../../assets/icons/cloud-data-connection.svg";
import ICON_SLACK from "../../../../assets/icons/brand-slack.svg";
import ICON_TEAMS from "../../../../assets/icons/brand-teams.svg";
import ICON_MAILCHIMP from "../../../../assets/icons/mail-heart.svg";
import ICON_SALESFORCE from "../../../../assets/icons/rosette-discount.svg";
import ICON_HUBSPOT from "../../../../assets/icons/cube-plus.svg";
import ICON_WEBHOOK from "../../../../assets/icons/world-share.svg";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const {
    local,
    fieldOptions,
    mappableFieldOptions,
    emailFieldOptions,
    slackTemplatePlaceholder,
    slackTemplateHint,
    teamsTemplatePlaceholder,
    teamsTemplateHint,
    mailchimpAudiences,
    mailchimpAudiencesLoading,
    mailchimpAudiencesError,
    fetchMailchimpAudiences,
    addSalesforceMapping,
    removeSalesforceMapping,
    addHubspotMapping,
    removeHubspotMapping,
} = ctx;

const integrationIcons = {
    zapier: ICON_ZAPIER,
    make: ICON_MAKE,
    slack: ICON_SLACK,
    teams: ICON_TEAMS,
    mailchimp: ICON_MAILCHIMP,
    salesforce: ICON_SALESFORCE,
    hubspot: ICON_HUBSPOT,
    webhook: ICON_WEBHOOK,
};
</script>
