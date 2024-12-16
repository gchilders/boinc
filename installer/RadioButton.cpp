// This file is part of BOINC.
// https://boinc.berkeley.edu
// Copyright (C) 2024 University of California
//
// BOINC is free software; you can redistribute it and/or modify it
// under the terms of the GNU Lesser General Public License
// as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// BOINC is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with BOINC.  If not, see <http://www.gnu.org/licenses/>.

#include "RadioButton.h"
#include "MsiHelper.h"
#include "JsonHelper.h"

RadioButton::RadioButton(const nlohmann::json& json,
    const std::string& property, InstallerStrings& installerStrings)
    : property(property) {
    JsonHelper::get(json, "Order", order);
    JsonHelper::get(json, "Value", value);
    JsonHelper::get(json, "X", x);
    JsonHelper::get(json, "Y", y);
    JsonHelper::get(json, "Width", width);
    JsonHelper::get(json, "Height", height);
    JsonHelper::get(json, "Text", text, installerStrings);
    JsonHelper::get(json, "Help", help, installerStrings);
}

MSIHANDLE RadioButton::getRecord() const {
    return MsiHelper::MsiRecordSet({ property, order, value, x, y, width,
        height, text, help });
}
