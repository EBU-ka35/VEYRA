const jwt = require("jsonwebtoken");

const JWT_SECRET =
    process.env.JWT_SECRET || "veyra-development-secret-key";


const authenticateToken = (req, res, next) => {

    const authHeader =
        req.headers.authorization;


    if (!authHeader) {

        return res.status(401).json({
            success: false,
            message: "Authentication required."
        });

    }


    const parts =
        authHeader.split(" ");


    if (
        parts.length !== 2 ||
        parts[0] !== "Bearer"
    ) {

        return res.status(401).json({
            success: false,
            message: "Invalid authentication format."
        });

    }


    const token = parts[1];


    try {

        const decoded =
            jwt.verify(
                token,
                JWT_SECRET
            );


        req.user = decoded;

        next();

    } catch (error) {

        return res.status(401).json({
            success: false,
            message: "Invalid or expired token."
        });

    }

};


module.exports = authenticateToken;