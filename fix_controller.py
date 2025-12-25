#!/usr/bin/env python3

# Read the file
with open('app/Http/Controllers/AcademicReportController.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Replace lines 325 and 326 (0-indexed so 324 and 325)
lines[324] = "            $studentId = $request->get('student_id');\n"
lines[325] = "            $groupId = $request->get('group_id');\n"

# Write back
with open('app/Http/Controllers/AcademicReportController.php', 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("File updated successfully!")
print(f"Line 325: {lines[324].strip()}")
print(f"Line 326: {lines[325].strip()}")
