const express = require("express");
const db = require("../db");
const authenticateToken = require("../middleware/auth");

const router = express.Router();

/*
====================================================
VEYRA PROJECTS API
====================================================

Routes:

GET    /api/projects
GET    /api/projects/:id
POST   /api/projects
PUT    /api/projects/:id
DELETE /api/projects/:id

All routes require authentication.
====================================================
*/


/*
====================================================
GET ALL PROJECTS
====================================================
GET /api/projects

Optional query parameters:

?status=planned
?status=progress
?status=review
?status=completed

?search=website
====================================================
*/

router.get("/", authenticateToken, async (req, res) => {
    try {
        const { status, search } = req.query;

        let sql = `
            SELECT
                p.id,
                p.name,
                p.owner_id,
                p.category,
                p.status,
                p.progress,
                p.created_at,
                p.updated_at,
                u.name AS owner_name,
                u.email AS owner_email
            FROM projects p
            LEFT JOIN users u
                ON p.owner_id = u.id
            WHERE 1 = 1
        `;

        const params = [];

        /*
        -----------------------------
        STATUS FILTER
        -----------------------------
        */

        if (status) {
            const allowedStatuses = [
                "planned",
                "progress",
                "review",
                "completed"
            ];

            if (!allowedStatuses.includes(status)) {
                return res.status(400).json({
                    success: false,
                    message: "Invalid project status."
                });
            }

            sql += " AND p.status = ?";
            params.push(status);
        }

        /*
        -----------------------------
        SEARCH
        -----------------------------
        */

        if (search) {
            sql += `
                AND (
                    p.name LIKE ?
                    OR p.category LIKE ?
                    OR u.name LIKE ?
                )
            `;

            const searchValue = `%${search.trim()}%`;

            params.push(
                searchValue,
                searchValue,
                searchValue
            );
        }

        sql += `
            ORDER BY p.created_at DESC
        `;

        const [projects] = await db.query(
            sql,
            params
        );

        return res.status(200).json({
            success: true,
            count: projects.length,
            projects
        });

    } catch (error) {

        console.error(
            "Get projects error:",
            error
        );

        return res.status(500).json({
            success: false,
            message: "Unable to retrieve projects."
        });
    }
});


/*
====================================================
GET SINGLE PROJECT
====================================================
GET /api/projects/:id
====================================================
*/

router.get("/:id", authenticateToken, async (req, res) => {
    try {

        const projectId =
            Number(req.params.id);

        if (
            !Number.isInteger(projectId) ||
            projectId <= 0
        ) {
            return res.status(400).json({
                success: false,
                message: "Invalid project ID."
            });
        }

        const [projects] = await db.query(
            `
            SELECT
                p.id,
                p.name,
                p.owner_id,
                p.category,
                p.status,
                p.progress,
                p.created_at,
                p.updated_at,
                u.name AS owner_name,
                u.email AS owner_email
            FROM projects p
            LEFT JOIN users u
                ON p.owner_id = u.id
            WHERE p.id = ?
            `,
            [projectId]
        );

        if (projects.length === 0) {
            return res.status(404).json({
                success: false,
                message: "Project not found."
            });
        }

        return res.status(200).json({
            success: true,
            project: projects[0]
        });

    } catch (error) {

        console.error(
            "Get project error:",
            error
        );

        return res.status(500).json({
            success: false,
            message: "Unable to retrieve project."
        });
    }
});


/*
====================================================
CREATE PROJECT
====================================================
POST /api/projects

Body:

{
    "name": "Website Redesign",
    "owner_id": 1,
    "category": "Web Development",
    "status": "progress",
    "progress": 50
}

owner_id is optional.
If omitted, the authenticated user becomes the owner.
====================================================
*/

router.post("/", authenticateToken, async (req, res) => {
    try {

        const {
            name,
            owner_id,
            category,
            status,
            progress
        } = req.body;

        /*
        -----------------------------
        NAME VALIDATION
        -----------------------------
        */

        if (
            typeof name !== "string" ||
            name.trim().length < 2
        ) {
            return res.status(400).json({
                success: false,
                message:
                    "Project name must contain at least 2 characters."
            });
        }

        const cleanName =
            name.trim();

        /*
        -----------------------------
        CATEGORY
        -----------------------------
        */

        let cleanCategory = null;

        if (
            category !== undefined &&
            category !== null &&
            category !== ""
        ) {

            if (
                typeof category !== "string"
            ) {
                return res.status(400).json({
                    success: false,
                    message:
                        "Project category must be text."
                });
            }

            cleanCategory =
                category.trim();
        }

        /*
        -----------------------------
        STATUS
        -----------------------------
        */

        const allowedStatuses = [
            "planned",
            "progress",
            "review",
            "completed"
        ];

        let cleanStatus =
            status || "planned";

        if (
            !allowedStatuses.includes(
                cleanStatus
            )
        ) {
            return res.status(400).json({
                success: false,
                message:
                    "Invalid project status."
            });
        }

        /*
        -----------------------------
        PROGRESS
        -----------------------------
        */

        let cleanProgress =
            progress === undefined ||
            progress === null ||
            progress === ""
                ? 0
                : Number(progress);

        if (
            !Number.isInteger(
                cleanProgress
            ) ||
            cleanProgress < 0 ||
            cleanProgress > 100
        ) {
            return res.status(400).json({
                success: false,
                message:
                    "Progress must be an integer between 0 and 100."
            });
        }

        /*
        -----------------------------
        OWNER
        -----------------------------
        */

        let ownerId =
            owner_id === undefined ||
            owner_id === null ||
            owner_id === ""
                ? req.user.id
                : Number(owner_id);

        if (
            !Number.isInteger(ownerId) ||
            ownerId <= 0
        ) {
            return res.status(400).json({
                success: false,
                message:
                    "Invalid owner ID."
            });
        }

        /*
        -----------------------------
        VERIFY OWNER EXISTS
        -----------------------------
        */

        const [owners] = await db.query(
            `
            SELECT id
            FROM users
            WHERE id = ?
            `,
            [ownerId]
        );

        if (owners.length === 0) {
            return res.status(400).json({
                success: false,
                message:
                    "Project owner does not exist."
            });
        }

        /*
        -----------------------------
        CREATE PROJECT
        -----------------------------
        */

        const [result] = await db.query(
            `
            INSERT INTO projects
                (
                    name,
                    owner_id,
                    category,
                    status,
                    progress
                )
            VALUES
                (?, ?, ?, ?, ?)
            `,
            [
                cleanName,
                ownerId,
                cleanCategory,
                cleanStatus,
                cleanProgress
            ]
        );

        /*
        -----------------------------
        RETURN CREATED PROJECT
        -----------------------------
        */

        const [projects] = await db.query(
            `
            SELECT
                p.id,
                p.name,
                p.owner_id,
                p.category,
                p.status,
                p.progress,
                p.created_at,
                p.updated_at,
                u.name AS owner_name,
                u.email AS owner_email
            FROM projects p
            LEFT JOIN users u
                ON p.owner_id = u.id
            WHERE p.id = ?
            `,
            [result.insertId]
        );

        return res.status(201).json({
            success: true,
            message:
                "Project created successfully.",
            project: projects[0]
        });

    } catch (error) {

        console.error(
            "Create project error:",
            error
        );

        return res.status(500).json({
            success: false,
            message:
                "Unable to create project."
        });
    }
});


/*
====================================================
UPDATE PROJECT
====================================================
PUT /api/projects/:id

All fields are optional.

Example:

{
    "name": "Updated Project",
    "category": "Cloud Engineering",
    "status": "completed",
    "progress": 100
}
====================================================
*/

router.put("/:id", authenticateToken, async (req, res) => {
    try {

        const projectId =
            Number(req.params.id);

        if (
            !Number.isInteger(projectId) ||
            projectId <= 0
        ) {
            return res.status(400).json({
                success: false,
                message: "Invalid project ID."
            });
        }

        /*
        -----------------------------
        CHECK PROJECT EXISTS
        -----------------------------
        */

        const [existingProjects] =
            await db.query(
                `
                SELECT *
                FROM projects
                WHERE id = ?
                `,
                [projectId]
            );

        if (
            existingProjects.length === 0
        ) {
            return res.status(404).json({
                success: false,
                message: "Project not found."
            });
        }

        const existing =
            existingProjects[0];

        const {
            name,
            owner_id,
            category,
            status,
            progress
        } = req.body;

        /*
        -----------------------------
        NAME
        -----------------------------
        */

        let cleanName =
            existing.name;

        if (name !== undefined) {

            if (
                typeof name !== "string" ||
                name.trim().length < 2
            ) {
                return res.status(400).json({
                    success: false,
                    message:
                        "Project name must contain at least 2 characters."
                });
            }

            cleanName =
                name.trim();
        }

        /*
        -----------------------------
        CATEGORY
        -----------------------------
        */

        let cleanCategory =
            existing.category;

        if (category !== undefined) {

            if (
                category === null ||
                category === ""
            ) {
                cleanCategory = null;

            } else if (
                typeof category === "string"
            ) {
                cleanCategory =
                    category.trim();

            } else {
                return res.status(400).json({
                    success: false,
                    message:
                        "Project category must be text."
                });
            }
        }

        /*
        -----------------------------
        STATUS
        -----------------------------
        */

        const allowedStatuses = [
            "planned",
            "progress",
            "review",
            "completed"
        ];

        let cleanStatus =
            existing.status;

        if (status !== undefined) {

            if (
                !allowedStatuses.includes(
                    status
                )
            ) {
                return res.status(400).json({
                    success: false,
                    message:
                        "Invalid project status."
                });
            }

            cleanStatus =
                status;
        }

        /*
        -----------------------------
        PROGRESS
        -----------------------------
        */

        let cleanProgress =
            existing.progress;

        if (progress !== undefined) {

            const numericProgress =
                Number(progress);

            if (
                !Number.isInteger(
                    numericProgress
                ) ||
                numericProgress < 0 ||
                numericProgress > 100
            ) {
                return res.status(400).json({
                    success: false,
                    message:
                        "Progress must be an integer between 0 and 100."
                });
            }

            cleanProgress =
                numericProgress;
        }

        /*
        -----------------------------
        OWNER
        -----------------------------
        */

        let ownerId =
            existing.owner_id;

        if (owner_id !== undefined) {

            if (
                owner_id === null ||
                owner_id === ""
            ) {
                ownerId = null;

            } else {

                const numericOwnerId =
                    Number(owner_id);

                if (
                    !Number.isInteger(
                        numericOwnerId
                    ) ||
                    numericOwnerId <= 0
                ) {
                    return res.status(400).json({
                        success: false,
                        message:
                            "Invalid owner ID."
                    });
                }

                ownerId =
                    numericOwnerId;

                /*
                Verify new owner exists.
                */

                const [owners] =
                    await db.query(
                        `
                        SELECT id
                        FROM users
                        WHERE id = ?
                        `,
                        [ownerId]
                    );

                if (
                    owners.length === 0
                ) {
                    return res.status(400).json({
                        success: false,
                        message:
                            "Project owner does not exist."
                    });
                }
            }
        }

        /*
        -----------------------------
        UPDATE DATABASE
        -----------------------------
        */

        await db.query(
            `
            UPDATE projects
            SET
                name = ?,
                owner_id = ?,
                category = ?,
                status = ?,
                progress = ?
            WHERE id = ?
            `,
            [
                cleanName,
                ownerId,
                cleanCategory,
                cleanStatus,
                cleanProgress,
                projectId
            ]
        );

        /*
        -----------------------------
        RETURN UPDATED PROJECT
        -----------------------------
        */

        const [projects] =
            await db.query(
                `
                SELECT
                    p.id,
                    p.name,
                    p.owner_id,
                    p.category,
                    p.status,
                    p.progress,
                    p.created_at,
                    p.updated_at,
                    u.name AS owner_name,
                    u.email AS owner_email
                FROM projects p
                LEFT JOIN users u
                    ON p.owner_id = u.id
                WHERE p.id = ?
                `,
                [projectId]
            );

        return res.status(200).json({
            success: true,
            message:
                "Project updated successfully.",
            project: projects[0]
        });

    } catch (error) {

        console.error(
            "Update project error:",
            error
        );

        return res.status(500).json({
            success: false,
            message:
                "Unable to update project."
        });
    }
});


/*
====================================================
DELETE PROJECT
====================================================
DELETE /api/projects/:id
====================================================
*/

router.delete("/:id", authenticateToken, async (req, res) => {
    try {

        const projectId =
            Number(req.params.id);

        if (
            !Number.isInteger(projectId) ||
            projectId <= 0
        ) {
            return res.status(400).json({
                success: false,
                message: "Invalid project ID."
            });
        }

        /*
        -----------------------------
        CHECK PROJECT EXISTS
        -----------------------------
        */

        const [projects] =
            await db.query(
                `
                SELECT id, name
                FROM projects
                WHERE id = ?
                `,
                [projectId]
            );

        if (projects.length === 0) {
            return res.status(404).json({
                success: false,
                message: "Project not found."
            });
        }

        /*
        -----------------------------
        DELETE PROJECT
        -----------------------------
        */

        await db.query(
            `
            DELETE FROM projects
            WHERE id = ?
            `,
            [projectId]
        );

        return res.status(200).json({
            success: true,
            message:
                "Project deleted successfully.",
            project: {
                id: projectId,
                name: projects[0].name
            }
        });

    } catch (error) {

        console.error(
            "Delete project error:",
            error
        );

        return res.status(500).json({
            success: false,
            message:
                "Unable to delete project."
        });
    }
});


module.exports = router;