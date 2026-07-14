const adminOverlayConfigs = {
    user: {
        label: "User",
        groupClass: "userGroup",
        rowSelector: ".userRow",
        deleteQuestion: "Are you sure you want to delete this user?",
        deleteDataAttr: "user",
        deleteValueKey: "platformUserId",

        fields: [
            {
                key: "platformUserId",
                placeholder: "Platform ID",
                type: "text",
                rowIndex: 0,
                disabledOnEdit: true
            },
            {
                key: "platform",
                placeholder: "Platform",
                type: "text",
                rowIndex: 1,
                defaultValue: "twitch"
            },
            {
                key: "username",
                placeholder: "Username",
                type: "text",
                rowIndex: 2
            },
            {
                key: "displayName",
                placeholder: "Display Name",
                type: "text",
                rowIndex: 3
            },
            {
                key: "gemBalance",
                placeholder: "Gem Balance",
                type: "number",
                rowIndex: 4
            }
        ],

        getDeleteDisplay(data) {
            let nameText = data.username || "";

            if (data.displayName && data.displayName !== data.username) {
                nameText += " (" + data.displayName + ")";
            }

            return nameText;
        },

        updateRow(index, data) {
            const rowChildren = $(".userRow[data-index='" + index + "']").children();

            rowChildren[2].innerText = data.username;
            rowChildren[3].innerText = data.displayName;
            rowChildren[4].innerText = data.gemBalance;
            rowChildren[5].innerHTML = "<span class=\"updatedDate\">Now</span>";
        },

        buildPostData(action, data) {
            return {
                domain: "user",
                action: action,
                platformUserId: data.platformUserId,
                platform: data.platform,
                username: data.username,
                displayName: data.displayName,
                gemBalance: data.gemBalance
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "User updated successfully";
            }

            return "User added successfully. Refresh the page to see the new user.";
        }
    },

    command: {
        label: "Command",
        groupClass: "commandGroup",
        rowSelector: ".commandRow",
        deleteQuestion: "Are you sure you want to delete this command?",
        deleteDataAttr: "command",
        deleteValueKey: "name",

        fields: [
            {
                key: "name",
                placeholder: "Command Name",
                type: "text",
                rowIndex: 0
            },
            {
                key: "description",
                placeholder: "Description",
                type: "textarea",
                rowIndex: 1,
                rows: 4
            },
            {
                key: "category",
                placeholder: "Category",
                type: "select",
                rowIndex: 2,
                options: [
                    { value: "Utility", label: "Utility" },
                    { value: "Playful", label: "Playful" },
                    { value: "Supportive", label: "Supportive" },
                    { value: "Other", label: "Other" }
                ],
                allowBlankOption: false
            },
            {
                key: "perms",
                placeholder: "Permissions",
                type: "select",
                rowIndex: 3,
                options: [
                    { value: "Everyone", label: "Everyone" },
                    { value: "VIPs", label: "VIPs" },
                    { value: "Mods", label: "Mods" }
                ],
                allowBlankOption: false
            }
        ],

        getDeleteDisplay(data) {
            return data.name || "";
        },

        updateRow(index, data) {
            const rowChildren = $(".commandRow[data-index='" + index + "']").children();

            rowChildren[0].innerText = data.name;
            rowChildren[1].innerText = data.description;
            rowChildren[2].innerText = data.category;
            rowChildren[3].innerText = data.perms;
        },

        buildPostData(action, data) {
            return {
                domain: "command",
                action: action,
                name: data.name,
                description: data.description,
                category: data.category,
                perms: data.perms
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "Command updated successfully";
            }

            return "Command added successfully. Refresh the page to see the new command.";
        }
    },

    quote: {
        label: "Quote",
        groupClass: "quoteGroup",
        rowSelector: ".quoteRow",
        deleteQuestion: "Are you sure you want to delete this quote?",
        deleteDataAttr: "quote",
        deleteValueKey: "id",

        fields: [
            {
                key: "id",
                placeholder: "Quote Number",
                type: "text",
                rowIndex: 0
            },
            {
                key: "text",
                placeholder: "Quote Text",
                type: "textarea",
                rowIndex: 1,
                rows: 4
            },
            {
                key: "speaker",
                placeholder: "Speaker",
                type: "text",
                rowIndex: 2
            },
            {
                key: "game",
                placeholder: "Game",
                type: "text",
                rowIndex: 3
            },
            {
                key: "date",
                placeholder: "Date",
                type: "text",
                rowIndex: 4
            },
            {
                key: "favorite",
                placeholder: "Favorite",
                type: "select",
                rowIndex: 5,
                options: [
                    { value: "1", label: "Yes" },
                    { value: "0", label: "No" }
                ],
                allowBlankOption: false
            }
        ],

        getDeleteDisplay(data) {
            return "Quote Number: " + data.id || "";
        },

        updateRow(index, data) {
            const rowChildren = $(".quoteRow[data-index='" + index + "']").children();

            rowChildren[1].innerText = data.text;
            rowChildren[2].innerText = data.speaker;
            rowChildren[3].innerText = data.game;
            rowChildren[4].innerText = data.date;
            rowChildren[5].innerText = data.favorite === "1" ? "Yes" : "No";
        },

        buildPostData(action, data) {
            console.log(data)
            return {
                domain: "quote",
                action: action,
                id: data.id,
                text: data.text,
                speaker: data.speaker,
                game: data.game,
                date: data.date,
                favorite: data.favorite
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "Quote updated successfully";
            }

            return "Quote added successfully. Refresh the page to see the new quote.";
        }
    },

    objective: {
        label: "Objective",
        groupClass: "objectiveGroup",
        rowSelector: ".objectiveRow",
        deleteQuestion: "Are you sure you want to delete this objective?",
        deleteDataAttr: "objective",
        deleteValueKey: "id",

        fields: [
            {
                key: "id",
                placeholder: "Objective Number",
                type: "text",
                rowIndex: 0
            },
            {
                key: "requirement",
                placeholder: "Objective Text",
                type: "textarea",
                rowIndex: 1,
                rows: 4
            },
            {
                key: "active",
                placeholder: "Active",
                type: "select",
                rowIndex: 2,
                options: [
                    { value: "1", label: "Yes" },
                    { value: "0", label: "No" }
                ],
                allowBlankOption: false

            }
        ],

        getDeleteDisplay(data) {
            return "Objective Number: " + data.id || "";
        },

        updateRow(index, data) {
            const rowChildren = $(".objectiveRow[data-index='" + index + "']").children();

            rowChildren[1].innerText = data.requirement;
            rowChildren[2].innerText = data.active === "1" ? "Yes" : "No";
        },

        buildPostData(action, data) {
            console.log(data)
            return {
                domain: "objective",
                action: action,
                id: data.id,
                requirement: data.requirement,
                active: data.active
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "Objective updated successfully";
            }

            return "Objective added successfully. Refresh the page to see the new objective.";
        }
    },

    monster: {
        label: "Monster",
        groupClass: "monsterGroup",
        rowSelector: ".monsterRow",
        deleteQuestion: "Are you sure you want to delete this monster?",
        deleteDataAttr: "monster",
        deleteValueKey: "id",

        fields: [
            {
                key: "id",
                placeholder: "Monster ID",
                type: "text",
                rowIndex: 0
            },
            {
                key: "gameName",
                placeholder: "Monster Name",
                type: "text",
                rowIndex: 1
            },
            {
                key: "customName",
                placeholder: "Custom Name",
                type: "text",
                rowIndex: 2
            }
        ],

        getDeleteDisplay(data) {
            return data.gameName || "";
        },

        updateRow(index, data) {
            const rowChildren = $(".monsterRow[data-index='" + index + "']").children();

            rowChildren[1].innerText = data.gameName;
            rowChildren[2].innerText = data.customName;
        },

        buildPostData(action, data) {
            console.log(data)
            return {
                domain: "monster",
                action: action,
                id: data.id,
                gameName: data.gameName,
                customName: data.customName
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "Monster name updated successfully";
            }

            return "Monster name added successfully. Refresh the page to see the new name.";
        }
    },

    lore: {
        label: "Lore",
        groupClass: "loreGroup",
        rowSelector: ".loreRow",
        deleteQuestion: "Are you sure you want to delete this lore?",
        deleteDataAttr: "lore",
        deleteValueKey: "id",

        fields: [
            {
                key: "chapterNumber",
                placeholder: "Chapter Number",
                type: "text",
                rowIndex: 0
            },
            {
                key: "chapterTitle",
                placeholder: "Chapter Title",
                type: "text",
                rowIndex: 1
            },
            {
                key: "chapterText",
                placeholder: "Chapter Text",
                type: "textarea",
                rowIndex: 2
            },
            {
                key: "chapterStreams",
                placeholder: "Chapter Streams",
                type: "text",
                rowIndex: 3
            }
        ],

        getDeleteDisplay(data) {
            let nameText = data.chapterTitle || "";

            if (data.chapterText && data.chapterText !== data.chapterTitle) {
                nameText += " (" + data.chapterText + ")";
            }

            return nameText;
        },

        updateRow(index, data) {
            const rowChildren = $(".loreRow[data-index='" + index + "']").children();

            rowChildren[2].innerText = data.chapterText;
            rowChildren[3].innerText = data.chapterStreams;
            rowChildren[4].innerHTML = "<span class=\"updatedDate\">Now</span>";
        },

        buildPostData(action, data) {
            return {
                domain: "lore",
                action: action,
                chapterNumber: data.chapterNumber,
                chapterTitle: data.chapterTitle,
                chapterText: data.chapterText,
                chapterStreams: data.chapterStreams
            };
        },

        getSuccessMessage(action) {
            if (action === "update") {
                return "Lore updated successfully";
            }

            return "Lore added successfully. Refresh the page to see the new lore.";
        }
    },
};

class AdminOverlayManager {
    constructor(configs) {
        this.configs = configs;
        this.overlaySelector = ".adminOverlay";
    }

    getConfig(domain) {
        const config = this.configs[domain];

        if (!config) {
            throw new Error("No admin overlay config found for domain: " + domain);
        }

        return config;
    }

    show(html) {
        $(this.overlaySelector).html(html);
        $(this.overlaySelector).removeClass("uiHidden");
    }

    hide() {
        $(this.overlaySelector).html("");
        $(this.overlaySelector).addClass("uiHidden");
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll("\"", "&quot;")
            .replaceAll("'", "&#39;");
    }

    escapeAttribute(value) {
        return this.escapeHtml(value);
    }

    readRowData(domain, children) {
        const config = this.getConfig(domain);
        const data = {};

        config.fields.forEach((field) => {
            const cell = children[field.rowIndex];
            let value = ""; 

            if (cell) {
                if (field.readValue) {
                    value = field.readValue(cell, children);
                } else {
                    value = cell.innerText.trim();
                }
            }

            data[field.key] = value;
        });

        return data;
    }

    buildTitle(titleText) {
        return (
            "<div class='editTitle'>" +
                "<h2>" + this.escapeHtml(titleText) + "</h2>" +
            "</div>"
        );
    }

    buildInput(field, value, mode) {
        const type = field.type || "text";
        const disabled = mode === "edit" && field.disabledOnEdit ? " disabled" : "";
        const fieldKey = this.escapeAttribute(field.key);
        const placeholder = this.escapeAttribute(field.placeholder || "");
        const safeValue = value ?? "";

        if (type === "textarea") {
            const rows = field.rows || 4;

            return (
                "<textarea " +
                    "data-field='" + fieldKey + "' " +
                    "placeholder='" + placeholder + "' " +
                    "rows='" + rows + "'" +
                    disabled +
                ">" + this.escapeHtml(safeValue) + "</textarea>"
            );
        }

        if (type === "select") {
            let html =
                "<select " +
                    "data-field='" + fieldKey + "'" +
                    disabled +
                ">";

            if (field.allowBlankOption) {
                html += "<option value=''>Select " + this.escapeHtml(field.placeholder || "option") + "</option>";
            }

            (field.options || []).forEach((option) => {
                const optionValue = typeof option === "string" ? option : option.value;
                const optionLabel = typeof option === "string" ? option : option.label;
                const selected = String(optionValue) === String(safeValue) ? " selected" : "";

                html +=
                    "<option value='" + this.escapeAttribute(optionValue) + "'" + selected + ">" +
                        this.escapeHtml(optionLabel) +
                    "</option>";
            });

            html += "</select>";
            return html;
        }

        if (type === "checkbox") {
            const checked = safeValue ? " checked" : "";

            return (
                "<label class='checkbox'>" +
                    "<input " +
                        "type='checkbox' " +
                        "data-field='" + fieldKey + "'" +
                        checked +
                        disabled +
                    ">" +
                    "<span>" + this.escapeHtml(field.placeholder || "") + "</span>" +
                "</label>"
            );
        }

        return (
            "<input " +
                "type='" + this.escapeAttribute(type) + "' " +
                "data-field='" + fieldKey + "' " +
                "placeholder='" + placeholder + "' " +
                "value='" + this.escapeAttribute(safeValue) + "'" +
                disabled +
            ">"
        );
    }

    buildFieldGroup(domain, mode, data) {
        const config = this.getConfig(domain);
        let html = "<div class='editFieldGroup " + this.escapeAttribute(config.groupClass) + "'>";

        config.fields.forEach((field) => {
            let value = "";

            if (mode === "edit") {
                value = data[field.key] ?? "";
            } else {
                value = field.defaultValue ?? "";
            }

            html += this.buildInput(field, value, mode);
        });

        html += "<div class='editButtonGroup'>";
        html += "<button class='saveButton' data-domain='" + this.escapeAttribute(domain) + "'";

        if (mode === "edit" && data.index !== null && data.index !== undefined) {
            html += " data-index='" + this.escapeAttribute(String(data.index)) + "'";
        }

        html += ">Save</button>";
        html += "<button class='cancelButton'>Cancel</button>";
        html += "</div>";
        html += "</div>";

        return html;
    }

    buildDeleteOverlay(domain, data) {
        const config = this.getConfig(domain);
        const displayText = config.getDeleteDisplay(data);
        const deleteValue = data[config.deleteValueKey] ?? "";

        let html = "";
        html += "<div class='editTitle'>";
        html += "<h2>Delete <br /><span class='toDelete'>" + this.escapeHtml(displayText) + "</span>?</h2>";
        html += "<p>" + this.escapeHtml(config.deleteQuestion) + "</p>";
        html += "<div class='editButtonGroup'>";
        html += "<button class='confirmDeleteButton' " +
            "data-domain='" + this.escapeAttribute(domain) + "' " +
            "data-" + this.escapeAttribute(config.deleteDataAttr) + "='" + this.escapeAttribute(String(deleteValue)) + "'>" +
            "Yes</button>";
        html += "<button class='cancelButton'>No!</button>";
        html += "</div>";
        html += "</div>";

        return html;
    }

    showEdit(domain, index, children) {
        const config = this.getConfig(domain);
        const data = this.readRowData(domain, children);
        data.index = index;

        let html = "";
        html += this.buildTitle("Edit " + config.label);
        html += this.buildFieldGroup(domain, "edit", data);

        this.show(html);
    }

    showAdd(domain) {
        const config = this.getConfig(domain);

        let html = "";
        html += this.buildTitle("Add " + config.label);
        html += this.buildFieldGroup(domain, "add", {});
        this.show(html);
    }

    showDelete(domain, children) {
        const data = this.readRowData(domain, children);
        const html = this.buildDeleteOverlay(domain, data);

        this.show(html);
    }

    getFormData(domain) {
        const config = this.getConfig(domain);
        const $group = $(this.overlaySelector).find("." + config.groupClass);
        const data = {};

        config.fields.forEach((field) => {
            const $field = $group.find("[data-field='" + field.key + "']");

            if (!$field.length) {
                data[field.key] = "";
                return;
            }

            if (field.type === "checkbox") {
                data[field.key] = $field.is(":checked");
                return;
            }
            
            data[field.key] = $field.val();
        });

        return data;
    }

    save(domain, index = null) {
        const config = this.getConfig(domain);
        const action = index !== null ? "update" : "add";
        const data = this.getFormData(domain);

        const postData = config.buildPostData(action, data);

        if (action === "update" && typeof config.updateRow === "function") {
            config.updateRow(index, data);
        }

        $.post("/api/AdminDbUpdate.php", postData, function(response) {
            console.log(response);
        });

        this.hide();
        new Alert("success", config.getSuccessMessage(action));
    }

    delete(domain, deleteValue) {
        const config = this.getConfig(domain);
        const postData = {
            domain: domain,
            action: "delete"
        };

        postData[config.deleteValueKey] = deleteValue;
        console.log(postData);

        $.post("/api/AdminDbUpdate.php", postData, function(response) {
            console.log(response);
        });

        this.hide();
        new Alert("success", config.label + " deleted successfully");
    }
}

const adminOverlayManager = new AdminOverlayManager(adminOverlayConfigs);