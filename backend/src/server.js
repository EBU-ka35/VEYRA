require("dotenv").config();

const express = require("express");
const cors = require("cors");

const db = require("./db");

const authRoutes = require("./routes/auth");
const projectRoutes = require("./routes/projects");

const authenticateToken = require("./middleware/auth");

const app = express();

const PORT = process.env.PORT || 5000;


/*
====================================================
GLOBAL MIDDLEWARE
====================================================
*/

app.use(cors());

app.use(express.json());


/*
====================================================
AUTH ROUTES
====================================================
*/

app.use(
    "/api/auth",
    authRoutes
);


/*
====================================================
PROJECT ROUTES
====================================================
*/

app.use(
    "/api/projects",
    projectRoutes
);


/*
====================================================
HEALTH CHECK
====================================================
*/

app.get(
    "/api/health",
    async (req, res) => {

        try {

            const [rows] =
                await db.query(
                    "SELECT 1 AS database_connected"
                );

            res.json({
                success: true,
                message:
                    "VEYRA backend and database are connected.",
                database:
                    rows[0].database_connected === 1
            });

        } catch (error) {

            console.error(
                "Database connection error:",
                error.message
            );

            res.status(500).json({
                success: false,
                message:
                    "Database connection failed."
            });
        }
    }
);


/*
====================================================
API INFORMATION
====================================================
*/

app.get(
    "/api",
    (req, res) => {

        res.json({
            name: "VEYRA API",
            version: "1.0.0",
            status: "online"
        });
    }
);


/*
====================================================
PROTECTED TEST ENDPOINT
====================================================
*/

app.get(
    "/api/protected",
    authenticateToken,
    (req, res) => {

        res.json({
            success: true,
            message:
                "You are authenticated and can access this protected resource.",
            user: req.user
        });
    }
);


/*
====================================================
404 HANDLER
====================================================
*/

app.use(
    (req, res) => {

        res.status(404).json({
            success: false,
            message:
                "API endpoint not found."
        });
    }
);


/*
====================================================
START SERVER
====================================================
*/

if (require.main === module) {

    app.listen(
        PORT,
        () => {

            console.log("");

            console.log(
                "================================="
            );

            console.log(
                "       VEYRA BACKEND"
            );

            console.log(
                "================================="
            );

            console.log(
                `Server: http://localhost:${PORT}`
            );

            console.log(
                `Health: http://localhost:${PORT}/api/health`
            );

            console.log(
                `Auth:   http://localhost:${PORT}/api/auth/status`
            );

            console.log(
                `Projects: http://localhost:${PORT}/api/projects`
            );

            console.log(
                "================================="
            );

            console.log("");
        }
    );
}


module.exports = app;